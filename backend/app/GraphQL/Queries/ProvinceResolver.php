<?php

namespace App\GraphQL\Queries;

use App\GraphQL\BaseResolver;
use App\Repositories\ProvinceRepository;

class ProvinceResolver extends BaseResolver
{
    public function __construct(private ProvinceRepository $provinceRepository) {}

    public function all($_, array $args)
    {
        return $this->safe(fn() => $this->provinceRepository->all());
    }
}
