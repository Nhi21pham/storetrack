<?php

namespace App\Validators;

class SupplierValidator
{
    public static function create(): array
    {
        return [
            'name'     => 'required|string|min:2|max:255',
            'email'    => 'nullable|email|max:255',
            'phone'    => 'nullable|string|regex:/^\d{10}$/',
            'address'  => 'nullable|string|max:500',
            'tax_code' => 'nullable|string|max:50',
        ];
    }

    public static function update(): array
    {
        return [
            'name'     => 'sometimes|string|min:2|max:255',
            'email'    => 'nullable|email|max:255',
            'phone'    => 'nullable|string|regex:/^\d{10}$/',
            'address'  => 'nullable|string|max:500',
            'tax_code' => 'nullable|string|max:50',
        ];
    }
}
