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

    public function deleteByPayment(int $paymentId): void
    {
        PaymentAllocation::where('payment_id', $paymentId)->delete();
    }

    public function existsForInvoice(int $invoiceId): bool
    {
        return PaymentAllocation::where('invoice_id', $invoiceId)->exists();
    }
}
