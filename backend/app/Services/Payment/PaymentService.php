<?php

namespace App\Services\Payment;

use App\Enums\ErrorCode;
use App\Enums\InvoicePaymentStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PartyTypeEnum;
use App\Enums\PermissionEnum;
use App\Exceptions\PaymentException;
use App\Models\Payment\Payment;
use App\Models\User;
use App\Repositories\Invoice\InvoiceRepository;
use App\Repositories\Payment\PaymentAllocationRepository;
use App\Repositories\Payment\PaymentRepository;
use App\Repositories\PartyRepository;
use App\Repositories\StoreRepository;
use App\Services\AuditLog\Loggers\PaymentAuditLogger;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    private const EPSILON = 0.0001;

    public function __construct(
        private PaymentRepository $paymentRepository,
        private PaymentAllocationRepository $allocationRepository,
        private InvoiceRepository $invoiceRepository,
        private PartyRepository $partyRepository,
        private StoreRepository $storeRepository,
        private PermissionService $permissionService,
        private PaymentAuditLogger $auditLogger,
    ) {}

    public function getForParty(User $user, int $storeId, int $partyId): Collection
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::CREATE_PAYMENT, $storeId);
        return $this->paymentRepository->allForParty($storeId, $partyId);
    }

    /** The party's still-owing invoices in this store — the options for a record-payment allocation. */
    public function getOpenInvoices(User $user, int $storeId, int $partyId): Collection
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::CREATE_PAYMENT, $storeId);
        $type = $this->resolveLedger($partyId, $storeId);
        return $this->invoiceRepository->openForParty($storeId, $partyId, $type->value);
    }

    /** Owner-only business view: a party's payments across every store in the business. */
    public function getForPartyInBusiness(User $user, int $businessId, int $partyId): Collection
    {
        $this->permissionService->authorizeBusinessOwner($user, $businessId);
        return $this->paymentRepository->allForPartyInBusiness($businessId, $partyId);
    }

    /** Owner-only business view: a party's still-owing invoices across every store. */
    public function getOpenInvoicesInBusiness(User $user, int $businessId, int $partyId): Collection
    {
        $this->permissionService->authorizeBusinessOwner($user, $businessId);
        $type = $this->resolveLedgerForBusiness($partyId, $businessId);
        return $this->invoiceRepository->openForPartyInBusiness($businessId, $partyId, $type->value);
    }

    /**
     * Record a payment against a party and apply it to the invoices the caller
     * chose, in the amounts they specified. Each targeted invoice is locked and
     * checked (belongs to the party, right type, amount within its balance); the
     * payment total is the sum of the allocations, and every touched invoice's
     * cached paid_amount + derived status is updated. One transaction, audited.
     */
    public function record(User $actor, int $storeId, array $data): Payment
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_PAYMENT, $storeId);

        $partyId = (int) $data['party_id'];
        $allocations = $data['allocations'] ?? [];
        if (empty($allocations)) {
            throw new PaymentException(
                ErrorCode::PAYMENT_INVALID_ALLOCATION,
                'A payment must apply to at least one invoice.'
            );
        }

        return DB::transaction(function () use ($actor, $storeId, $data, $partyId, $allocations) {
            $type = $this->resolveLedger($partyId, $storeId);

            $applied = $this->validateAllocations($allocations, $storeId, $partyId, $type);
            $total = round(array_sum(array_map(fn ($entry) => $entry['amount'], $applied)), 2);

            $payment = $this->paymentRepository->create([
                'store_id'   => $storeId,
                'party_id'   => $partyId,
                'amount'     => $total,
                'paid_at'    => $data['paid_at'],
                'method'     => $data['method'],
                'note'       => $data['note'] ?? null,
                'created_by' => (int) $actor->id,
            ]);

            foreach ($applied as $entry) {
                $this->applyAllocation($payment, $entry['invoice'], $entry['amount']);
            }

            $this->auditLogger->paymentRecorded($actor, $payment);

            return $this->paymentRepository->findById((int) $payment->id);
        });
    }

    public function delete(User $actor, int $id): void
    {
        DB::transaction(function () use ($actor, $id) {
            $payment = $this->paymentRepository->lockById($id);
            if (!$payment) {
                throw new PaymentException(ErrorCode::PAYMENT_NOT_FOUND, 'Payment not found.');
            }
            $storeId = (int) $payment->store_id;
            $this->permissionService->authorizeStore($actor, PermissionEnum::DELETE_PAYMENT, $storeId);

            $this->releaseAllocations($id);

            $paymentId = (int) $payment->id;
            $partyId   = (int) $payment->party_id;
            $amount    = (float) $payment->amount;

            $this->paymentRepository->delete($payment);
            $this->auditLogger->paymentDeleted($actor, $paymentId, $partyId, $amount, $storeId);
        });
    }

    /**
     * Lock + validate every targeted invoice and return the parsed allocations as
     * [['invoice' => Invoice, 'amount' => float], ...].
     */
    private function validateAllocations(array $allocations, int $storeId, int $partyId, InvoiceTypeEnum $type): array
    {
        $applied = [];

        foreach ($allocations as $allocation) {
            $invoiceId = (int) $allocation['invoice_id'];
            $amount = round((float) $allocation['amount'], 2);

            if ($amount <= 0) {
                throw new PaymentException(
                    ErrorCode::PAYMENT_INVALID_AMOUNT,
                    'Each allocation amount must be greater than zero.'
                );
            }
            if (isset($applied[$invoiceId])) {
                throw new PaymentException(
                    ErrorCode::PAYMENT_INVALID_ALLOCATION,
                    'An invoice can only appear once in a payment.'
                );
            }

            $invoice = $this->invoiceRepository->lockById($invoiceId);
            if (
                !$invoice
                || (int) $invoice->store_id !== $storeId
                || (int) $invoice->party_id !== $partyId
                || $invoice->type !== $type
            ) {
                throw new PaymentException(
                    ErrorCode::PAYMENT_INVALID_ALLOCATION,
                    'Invoice not found for this party.'
                );
            }

            $balance = round((float) $invoice->grand_total - (float) $invoice->paid_amount, 2);
            if ($amount > $balance + self::EPSILON) {
                throw new PaymentException(
                    ErrorCode::PAYMENT_EXCEEDS_BALANCE,
                    "Payment of {$this->money($amount)} for invoice {$invoice->code} exceeds its {$this->money($balance)} balance."
                );
            }

            $applied[$invoiceId] = ['invoice' => $invoice, 'amount' => $amount];
        }

        return $applied;
    }

    /** Write an allocation row and roll its amount into the invoice's paid_amount + status. */
    private function applyAllocation(Payment $payment, Model $invoice, float $amount): void
    {
        $this->allocationRepository->create([
            'payment_id' => (int) $payment->id,
            'invoice_id' => (int) $invoice->id,
            'amount'     => $amount,
        ]);

        $newPaid = round((float) $invoice->paid_amount + $amount, 2);
        $this->invoiceRepository->update($invoice, [
            'paid_amount'    => $newPaid,
            'payment_status' => InvoicePaymentStatusEnum::fromAmounts($newPaid, (float) $invoice->grand_total)->value,
        ]);
    }

    /**
     * Undo a payment's allocations: return each applied amount to its invoice's
     * paid_amount, re-derive the status, then drop the allocation rows.
     */
    private function releaseAllocations(int $paymentId): void
    {
        $allocations = $this->allocationRepository->lockByPayment($paymentId);

        foreach ($allocations as $allocation) {
            $invoice = $this->invoiceRepository->lockById((int) $allocation->invoice_id);
            if (!$invoice) {
                continue;
            }

            $newPaid = round((float) $invoice->paid_amount - (float) $allocation->amount, 2);
            if ($newPaid < 0) {
                $newPaid = 0.0;
            }
            $this->invoiceRepository->update($invoice, [
                'paid_amount'    => $newPaid,
                'payment_status' => InvoicePaymentStatusEnum::fromAmounts($newPaid, (float) $invoice->grand_total)->value,
            ]);
        }

        $this->allocationRepository->deleteByPayment($paymentId);
    }

    /** Validate the party belongs to the store's business and return the invoice type it owes on. */
    private function resolveLedger(int $partyId, int $storeId): InvoiceTypeEnum
    {
        $businessId = (int) $this->storeRepository->findById($storeId)?->business_id;
        return $this->resolveLedgerForBusiness($partyId, $businessId);
    }

    private function resolveLedgerForBusiness(int $partyId, int $businessId): InvoiceTypeEnum
    {
        $party = $this->partyRepository->findWithLedgerRelations($partyId);
        if (!$party) {
            throw new PaymentException(ErrorCode::PAYMENT_PARTY_INVALID, 'Party not found.');
        }

        return match ($party->type) {
            PartyTypeEnum::CUSTOMER => $this->assertLedger($party->customer, $businessId, InvoiceTypeEnum::SALE, 'Customer'),
            PartyTypeEnum::SUPPLIER => $this->assertLedger($party->supplier, $businessId, InvoiceTypeEnum::PURCHASE, 'Supplier'),
            default => throw new PaymentException(
                ErrorCode::PAYMENT_PARTY_INVALID,
                'Payments can only be recorded for a customer or supplier.'
            ),
        };
    }

    /** Confirm the party's customer/supplier record belongs to the store's business. */
    private function assertLedger(?Model $record, int $businessId, InvoiceTypeEnum $type, string $label): InvoiceTypeEnum
    {
        if (!$record || (int) $record->business_id !== $businessId) {
            throw new PaymentException(ErrorCode::PAYMENT_PARTY_INVALID, "{$label} not found in this business.");
        }
        return $type;
    }

    private function money(float $amount): string
    {
        return number_format($amount, 0, ',', '.') . ' ₫';
    }
}
