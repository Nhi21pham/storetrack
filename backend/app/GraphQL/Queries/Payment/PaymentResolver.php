<?php

namespace App\GraphQL\Queries\Payment;

use App\GraphQL\BaseResolver;
use App\Services\Payment\PaymentService;

class PaymentResolver extends BaseResolver
{
    public function __construct(private PaymentService $paymentService) {}

    public function forParty($_, array $args)
    {
        return $this->safe(function () use ($args) {
            return $this->paymentService->getForParty(
                $this->user(),
                (int) $args['store_id'],
                (int) $args['party_id'],
            );
        });
    }
}
