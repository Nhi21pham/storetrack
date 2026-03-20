<?php

namespace App\Http\Controllers;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function register(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|min:8|confirmed',
    ]);

    DB::beginTransaction();
    try {
        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Generate 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Save code to database
        DB::table('verification_codes')->insert([
            'email' => $user->email,
            'code' => $code,
            'expires_at' => Carbon::now()->addMinutes(10),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Send email
        Mail::to($user->email)->send(new VerificationCodeMail($code));

        DB::commit();

        return response()->json([
            'message' => 'Registration successful! Please check your email for the verification code.',
            'email' => $user->email
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['message' => 'Registration failed. Please try again.'], 500);
    }
}
}
