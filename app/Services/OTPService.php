<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OTPService
{
    protected $expirationTime = 10; // minutes

    /**
     * Generate and store OTP for phone number
     */
    public function generateOTP(string $phoneNumber): string
    {
        $otp = str_pad((string)rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $key = "otp:{$phoneNumber}";
        
        Cache::put($key, [
            'otp' => $otp,
            'phone' => $phoneNumber,
            'attempts' => 0,
        ], now()->addMinutes($this->expirationTime));

        return $otp;
    }

    /**
     * Verify OTP for phone number
     */
    public function verifyOTP(string $phoneNumber, string $otp): bool
    {
        $key = "otp:{$phoneNumber}";
        $data = Cache::get($key);

        if (!$data) {
            return false;
        }

        // Check attempts (max 5 attempts)
        if ($data['attempts'] >= 5) {
            Cache::forget($key);
            return false;
        }

        // Increment attempts
        $data['attempts']++;
        Cache::put($key, $data, now()->addMinutes($this->expirationTime));

        if ($data['otp'] === $otp) {
            Cache::forget($key);
            return true;
        }

        return false;
    }

    /**
     * Check if OTP exists for phone number
     */
    public function hasOTP(string $phoneNumber): bool
    {
        return Cache::has("otp:{$phoneNumber}");
    }

    /**
     * Get remaining attempts for phone number
     */
    public function getRemainingAttempts(string $phoneNumber): int
    {
        $key = "otp:{$phoneNumber}";
        $data = Cache::get($key);

        if (!$data) {
            return 0;
        }

        return 5 - $data['attempts'];
    }
}
