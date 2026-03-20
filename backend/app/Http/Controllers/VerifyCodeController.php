<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VerifyCodeController extends Controller
{
    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $verification = DB::table('verification_codes')
            ->where('email', $request->email)
            ->where('code', $request->code)
            ->first();

        if (!$verification) {
            return response()->json(['message' => 'Invalid verification code.'], 422);
        }

        if (Carbon::now()->isAfter($verification->expires_at)) {
            return response()->json(['message' => 'Verification code has expired.'], 422);
        }

        // Mark user as verified
        User::where('email', $request->email)->update(['email_verified_at' => Carbon::now()]);

        // Delete used code
        DB::table('verification_codes')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Email verified successfully!'], 200);
    }
}
