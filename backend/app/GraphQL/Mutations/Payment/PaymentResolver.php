<?php

namespace App\GraphQL\Mutations\Payment;

use App\GraphQL\BaseResolver;
use App\Services\Payment\PaymentService;

class PaymentResolver extends BaseResolver
{
    public function __construct(private PaymentService $paymentService) {}

    public function record($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $storeId = (int) $args['store_id'];
            unset($args['store_id']);
            return $this->paymentService->record($this->user(), $storeId, $args);
        });
    }

    public function delete($_, array $args): bool
    {
        return $this->safe(function () use ($args) {
            $this->paymentService->delete($this->user(), (int) $args['id']);
            return true;
        });
    }
}
