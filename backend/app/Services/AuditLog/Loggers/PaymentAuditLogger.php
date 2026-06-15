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
        $this->log($storeId, $actor, AuditObjectType::PAYMENT, AuditAction::CREATED,
            self::actor($actor) . " has RECORDED a payment of {$payment->amount}.",
            [
                'payment_id'  => $payment->id,
                'party_id'    => $payment->party_id,
                'amount'      => $payment->amount,
                'method'      => $payment->method->value,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function paymentDeleted(User $actor, int $paymentId, int $partyId, float $amount, int $storeId): void
    {
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::PAYMENT, AuditAction::DELETED,
            self::actor($actor) . " has DELETED a payment of {$amount}.",
            [
                'payment_id'  => $paymentId,
                'party_id'    => $partyId,
                'amount'      => $amount,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }
}
