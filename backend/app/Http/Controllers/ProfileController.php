<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'name_kh' => 'nullable|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:50',
        ]);
        $user->update($data);

        return AuthController::payload($user->fresh());
    }

    public function password(Request $request)
    {
        abort_unless($request->user()->can('profile_password_edit'), 403);

        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return response()->json(['message' => 'Current password is incorrect.', 'errors' => ['current_password' => ['Current password is incorrect.']]], 422);
        }

        $request->user()->update(['password' => $data['password']]);

        return response()->json(['message' => 'Password changed']);
    }

    public function signature(Request $request)
    {
        $request->validate(['signature' => 'required|image|max:2048']);
        $user = $request->user();

        if ($user->signature) {
            Storage::disk('local')->delete($user->signature);
        }
        $user->update(['signature' => $request->file('signature')->store('signatures', 'local')]);

        return AuthController::payload($user->fresh());
    }

    public function deleteSignature(Request $request)
    {
        $user = $request->user();
        if ($user->signature) {
            Storage::disk('local')->delete($user->signature);
            $user->update(['signature' => null]);
        }

        return AuthController::payload($user->fresh());
    }

    public function photo(Request $request)
    {
        $request->validate(['photo' => 'required|image|max:2048']);
        $user = $request->user();
        $oldPhoto = $user->photo;
        $user->update(['photo' => $request->file('photo')->store('profile-photos', 'local')]);

        if ($oldPhoto) {
            Storage::disk('local')->delete($oldPhoto);
        }

        return AuthController::payload($user->fresh());
    }

    public function deletePhoto(Request $request)
    {
        $user = $request->user();
        if ($user->photo) {
            Storage::disk('local')->delete($user->photo);
            $user->update(['photo' => null]);
        }

        return AuthController::payload($user->fresh());
    }

    // ---------- Two-factor authentication ----------

    /** Step 1: create a secret and return the otpauth:// link for the QR code. */
    public function twoFactorSetup(Request $request)
    {
        $user = $request->user();
        abort_if($user->two_factor_enabled, 422, 'Two-factor authentication is already enabled.');

        $secret = Totp::generateSecret();
        $user->update(['two_factor_secret' => $secret]);

        return [
            'secret' => $secret,
            'otpauth_url' => Totp::uri($secret, $user->email, AppSetting::get('app_name', 'ODF')),
        ];
    }

    /** Step 2: the user types the 6-digit code from the app to confirm. */
    public function twoFactorConfirm(Request $request)
    {
        $code = $request->validate(['code' => 'required|string'])['code'];
        $user = $request->user();
        abort_unless($user->two_factor_secret && Totp::verify($user->two_factor_secret, $code), 422, 'The code is not correct. Please try again.');
        $user->update(['two_factor_enabled' => true]);

        return AuthController::payload($user->fresh());
    }

    public function twoFactorDisable(Request $request)
    {
        $password = $request->validate(['password' => 'required|string'])['password'];
        $user = $request->user();
        abort_unless(Hash::check($password, $user->password), 422, 'Password is incorrect.');
        $user->update(['two_factor_enabled' => false, 'two_factor_secret' => null]);

        return AuthController::payload($user->fresh());
    }

    /** Delete my own account (needs the password). */
    public function destroy(Request $request)
    {
        $password = $request->validate(['password' => 'required|string'])['password'];
        $user = $request->user();
        abort_unless(Hash::check($password, $user->password), 422, 'Password is incorrect.');
        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }
}
