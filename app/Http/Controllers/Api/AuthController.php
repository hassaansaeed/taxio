<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Validation\Rules\Password;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;
use App\Notifications\VerifyEmail;

class AuthController extends Controller
{
    public function register(Request $request){
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Trigger the Registered event
        event(new Registered($user));

        // Manually send verification email
        $user->sendEmailVerificationNotification();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful. Please check your email for verification.',
            'user' => $user,
            'token' => $token,
            'email_verified' => false
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($validated)) {
            return response()->json([
                'message' => 'Invalid login credentials'
            ], 401);
        }

        $user = User::where('email', $validated['email'])->firstOrFail();
        
        if (!$user->hasVerifiedEmail()) {
            // Resend verification email if not verified
            $user->sendEmailVerificationNotification();
            
            return response()->json([
                'message' => 'Please verify your email address. A new verification link has been sent.',
                'user' => $user,
                'token' => $user->createToken('auth_token')->plainTextToken,
                'email_verified' => false
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
            'email_verified' => true
        ]);
    }

    public function googleLogin(){
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::firstOrCreate(
                ['email' => $googleUser->email],
                [
                    'name' => $googleUser->name,
                    'password' => bcrypt(Str::random(16)),
                    'google_id' => $googleUser->id,
                ]
            );

            // Auto-verify email for Google users
            if (!$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            Auth::login($user);
            return redirect()->intended('/dashboard');

        } catch (\Exception $e) {
            return redirect('/login')->withErrors(['google' => 'Login failed: ' . $e->getMessage()]);
        }
    }

    // 2nd Method
//    public function googleLogin(Request $request){
//        $token = $request->input('code');
//        try {
//            $googleUser = Socialite::driver('google')->userFromToken($token);
//            $user = User::firstOrCreate(
//                ['email' => $googleUser->email],
//                [
//                    'name' => $googleUser->name,
//                    'password' => bcrypt(Str::random(16)), // Random password for OAuth users
//                    'google_id' => $googleUser->id,
//                ]
//            );
//
//            $authToken = $user->createToken('auth_token')->plainTextToken;
//
//            return response()->json([
//                'user' => $user,
//                'token' => $authToken,
//            ]);
//        } catch (\Exception $e) {
//            return response()->json(['error' => 'Invalid credentials'], 401);
//        }
//    }

    public function logout(Request $request){
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }
}
