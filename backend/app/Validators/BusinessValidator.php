<?php

namespace App\Validators;

class BusinessValidator
{
    public static function create(): array
    {
        return [
            'name' => 'required|string|min:2|max:255',
            'tax_code' => 'required|string|regex:/^\d+$/|min:5|max:50|unique:businesses,tax_code',
            'address' => 'nullable|string|max:500',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|regex:/^\d{10}$/',
        ];
    }

    public static function update(): array
    {
        return [
            'name' => 'sometimes|string|min:2|max:255',
            'tax_code' => 'sometimes|string|regex:/^\d+$/|min:5|max:50',
            'address' => 'nullable|string|max:500',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|regex:/^\d{10}$/',
        ];
    }
}