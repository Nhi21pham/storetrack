<?php

namespace App\Validators;

class BankAccountValidator
{
    public static function create(): array
    {
        return [
            'bank_id'             => 'required|integer|exists:banks,id',
            'province_id'         => 'nullable|integer|exists:provinces,id',
            'account_number'      => 'required|string|min:4|max:50|regex:/^[0-9A-Za-z]+$/',
            'account_holder_name' => 'nullable|string|max:255',
            'branch'              => 'nullable|string|max:255',
        ];
    }

    public static function update(): array
    {
        return [
            'bank_id'             => 'sometimes|integer|exists:banks,id',
            'province_id'         => 'nullable|integer|exists:provinces,id',
            'account_number'      => 'sometimes|string|min:4|max:50|regex:/^[0-9A-Za-z]+$/',
            'account_holder_name' => 'nullable|string|max:255',
            'branch'              => 'nullable|string|max:255',
        ];
    }
}
