<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Twilio\Rest\Client;

class PhoneVerificationController extends Controller
{
    protected $twilioClient;

    public function __construct()
    {
//        $this->middleware('auth:sanctum');

        // Initialize Twilio client
        $this->twilioClient = new Client(
            config('services.twilio.sid'),
            config('services.twilio.auth_token')
        );
    }

    /**
     * Send verification code to phone number
     */
    public function send(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[1-9]\d{1,14}$/'],
        ]);

        $user = $request->user();
        $phone = $request->phone;

        // Generate a 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store the code in cache for 10 minutes
        Cache::put(
            'phone_verification_' . $phone,
            [
                'code' => Hash::make($code),
                'attempts' => 0
            ],
            now()->addMinutes(10)
        );

        try {
            // Send SMS using Twilio
            $this->twilioClient->messages->create(
                $phone,
                [
                    'from' => config('services.twilio.from'),
                    'body' => "Your Taxio verification code is: {$code}"
                ]
            );

            return response()->json([
                'message' => 'Verification code sent successfully',
                'phone' => $phone
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send verification code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify the phone number with the code
     */
    public function verify(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[1-9]\d{1,14}$/'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();
        $phone = $request->phone;
        $code = $request->code;

        $cachedData = Cache::get('phone_verification_' . $phone);

        if (!$cachedData) {
            return response()->json([
                'message' => 'Verification code has expired'
            ], 400);
        }


        if ($cachedData['attempts'] >= 3) {
            Cache::forget('phone_verification_' . $phone);
            return response()->json([
                'message' => 'Too many attempts. Please request a new code.'
            ], 400);
        }

        if (!Hash::check($code, $cachedData['code'])) {
            Cache::put(
                'phone_verification_' . $phone,
                [
                    'code' => $cachedData['code'],
                    'attempts' => $cachedData['attempts'] + 1
                ],
                now()->addMinutes(10)
            );

            return response()->json([
                'message' => 'Invalid verification code'
            ], 400);
        }

        // Update user's phone number and mark as verified
        $user->update([
            'phone' => $phone,
            'phone_verified_at' => now()
        ]);

        // Clear the verification code from cache
        Cache::forget('phone_verification_' . $phone);

        return response()->json([
            'message' => 'Phone number verified successfully',
            'phone' => $phone,
            'verified' => true
        ]);
    }

    /**
     * Check phone verification status
     */
    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'phone' => $user->phone,
            'verified' => !is_null($user->phone_verified_at),
            'verified_at' => $user->phone_verified_at
        ]);
    }
}
