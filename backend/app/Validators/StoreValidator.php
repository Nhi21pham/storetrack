<?php

namespace App\Validators;

class StoreValidator
{
    public static function create(): array
    {
        return [
            'business_id' => 'required|exists:businesses,id',
            'name' => 'required|string|min:2|max:255',
            'address' => 'nullable|string|max:500',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|regex:/^\d{10}$/',
        ];
    }

    public static function update(): array
    {
        return [
            'name' => 'sometimes|string|min:2|max:255',
            'address' => 'nullable|string|max:500',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|regex:/^\d{10}$/',
        ];
    }
}