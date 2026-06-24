<?php

namespace App\Services\AuditLog\Loggers;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\Payment\Payment;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLog\AuditLogger;

class PaymentAuditLogger extends AuditLogger
{
    public function paymentRecorded(User $actor, Payment $payment): void
    {
        $storeId = (int) $payment->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $invoices = $payment->allocations()
            ->with('invoice')
            ->get()
            ->map(fn ($allocation) => $allocation->invoice?->code)
            ->filter()
            ->implode(', ');
        $this->log($storeId, $actor, AuditObjectType::PAYMENT, AuditAction::CREATED,
            self::actor($actor) . " has RECORDED a payment of {$payment->amount} for invoice {$invoices}.",
            [
                'payment_id'  => $payment->id,
                'party_id'    => $payment->party_id,
                'amount'      => $payment->amount,
                'method'      => $payment->method->value,
                'invoices'    => $invoices,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function paymentDeleted(User $actor, int $paymentId, int $partyId, float $amount, int $storeId, string $invoices = ''): void
    {
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::PAYMENT, AuditAction::DELETED,
            self::actor($actor) . " has DELETED a payment of {$amount} from invoice {$invoices}.",
            [
                'payment_id'  => $paymentId,
                'party_id'    => $partyId,
                'amount'      => $amount,
                'invoices'    => $invoices,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }
}
