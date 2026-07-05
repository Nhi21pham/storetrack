<?php

namespace App\Repositories\Payment;

use App\Models\Payment\PaymentAllocation;
use Illuminate\Database\Eloquent\Collection;

class PaymentAllocationRepository
{
    public function create(array $data): PaymentAllocation
    {
        return PaymentAllocation::create($data);
    }

    public function update(PaymentAllocation $allocation, array $data): PaymentAllocation
    {
        $allocation->update($data);
        return $allocation;
    }

    /**
     * A payment's allocations, locked for update. Used to undo each one against
     * its invoice's paid_amount when the payment is deleted.
     */
    public function lockByPayment(int $paymentId): Collection
    {
        return PaymentAllocation::where('payment_id', $paymentId)
            ->lockForUpdate()
            ->get();
    }

    /**
     * Every allocation pointing at one invoice, across all payments, locked for
     * update. Used to re-home an invoice's payments when its party changes.
     */
    public function lockByInvoice(int $invoiceId): Collection
    {
        return PaymentAllocation::where('invoice_id', $invoiceId)
            ->lockForUpdate()
            ->get();
    }

    public function countByPayment(int $paymentId): int
    {
        return PaymentAllocation::where('payment_id', $paymentId)->count();
    }

    public function deleteByPayment(int $paymentId): void
    {
        PaymentAllocation::where('payment_id', $paymentId)->delete();
    }

    public function existsForInvoice(int $invoiceId): bool
    {
        return PaymentAllocation::where('invoice_id', $invoiceId)->exists();
    }
}
