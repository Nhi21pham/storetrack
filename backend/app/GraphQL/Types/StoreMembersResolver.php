<?php

namespace App\GraphQL\Types;

use App\Models\Store;

class StoreMembersResolver
{
    public function __invoke(Store $store)
    {
        return $store->users->map(fn($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->pivot->role,
        ]);
    }
}