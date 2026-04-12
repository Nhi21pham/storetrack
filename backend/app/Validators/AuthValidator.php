<?php

namespace App\Validators;

class AuthValidator
{
    public static function register(): array
    {
        return [
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public static function login(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ];
    }

    public static function verifyCode(): array
    {
        return [
            'email' => 'required|email',
            'code' => 'required|string|size:6|regex:/^\d+$/',
        ];
    }

    public static function forgotPassword(): array
    {
        return [
            'email' => 'required|email',
        ];
    }

    public static function resetPassword(): array
    {
        return [
            'email' => 'required|email',
            'code' => 'required|string|size:6|regex:/^\d+$/',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public static function updatePassword(): array
    {
        return [
            'old_password' => 'required|string|min:8',
            'new_password' => 'required|string|min:8|confirmed',
        ];
    }

    public static function updateUser(): array
    {
        return [
            'name' => 'sometimes|string|min:2|max:255'
        ];
    }
}