<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile
     */
    public function show(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }

    /**
     * Update the authenticated user's profile
     */
    public function update(Request $request){
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
        ]);

        if (isset($validated['photo'])) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $path = $request->file('photo')->store('profile-photos', 'public');
            $validated['profile_photo_path'] = $path;
            unset($validated['photo']);
        }

        if ($user->email !== $validated['email']) {
            $validated['email_verified_at'] = null;
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh(),
            'photo_url' => $user->profile_photo_path ? Storage::disk('public')->url($user->profile_photo_path) : null,
        ]);
    }

    /**
     * Update the authenticated user's password
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password'])
        ]);

        return response()->json([
            'message' => 'Password updated successfully'
        ]);
    }

    /**
     * Delete the authenticated user's profile photo
     */
    public function deleteProfilePhoto(Request $request){
        $user = $request->user();

        if ($user->profile_photo) {
            Storage::delete($user->profile_photo);
            $user->update(['profile_photo' => null]);
        }

        return response()->json([
            'message' => 'Profile photo deleted successfully'
        ]);
    }

}
