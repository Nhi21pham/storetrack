<?php

namespace App\GraphQL\Types;

use App\Enums\PartyTypeEnum;
use App\Models\Party;

class PartyDisplayNameResolver
{
    public function __invoke(Party $party): ?string
    {
        $type = $party->type instanceof PartyTypeEnum
            ? $party->type
            : PartyTypeEnum::from((string) $party->type);

        return match ($type) {
            PartyTypeEnum::BUSINESS => $party->business?->name,
            PartyTypeEnum::CUSTOMER => $party->customer?->name,
            PartyTypeEnum::SUPPLIER => $party->supplier?->name,
            default                 => null,
        };
    }
}
