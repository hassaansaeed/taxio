<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;


class AuthController extends Controller
{

    public function register(Request $request){

        try {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|unique:users',
                'password' => 'required|string|min:6|confirmed',
            ]);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Invalid credentials',
                    'errors' => [
                        'email' => ['The provided credentials are incorrect.']
                    ]
                ], 401);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        }
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

            $authToken = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $authToken,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
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

        $request->user()->tokens()->delete(); // Delete all tokens
        return response()->json(['message' => 'Logged out']);
    }
}
