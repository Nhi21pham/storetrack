<?php

namespace App\GraphQL\Queries;

use App\GraphQL\BaseResolver;
use App\Repositories\BusinessRepository;
use App\Services\BusinessService;

class BusinessResolver extends BaseResolver
{
    public function __construct(
        private BusinessService $businessService,
        private BusinessRepository $businessRepository
    ) {}

    public function myBusinesses($_, array $args)
    {
        return $this->businessService->getUserBusinesses($this->user());
    }

    public function getBusiness($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $user = $this->user();
            $business = $this->businessRepository->findById((int) $args['id']);

            if (!$business || $business->owner_id !== $user->id) {
                throw new \Exception('Business not found.');
            }

            return $business;
        });
    }
    public function accessibleBusinesses($_, array $args)
    {
        return $this->businessService->getAccessibleBusinesses($this->user());
    }
}