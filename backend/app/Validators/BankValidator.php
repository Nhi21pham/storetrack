<?php

namespace App\Validators;

class BankValidator
{
    public static function create(): array
    {
        return [
            'short_name'   => 'required|string|min:1|max:50',
            'full_name_vi' => 'required|string|min:2|max:255',
            'full_name_en' => 'required|string|min:2|max:255',
        ];
    }

    public static function update(): array
    {
        return [
            'short_name'   => 'sometimes|string|min:1|max:50',
            'full_name_vi' => 'sometimes|string|min:2|max:255',
            'full_name_en' => 'sometimes|string|min:2|max:255',
            'is_active'    => 'sometimes|boolean',
        ];
    }
}
