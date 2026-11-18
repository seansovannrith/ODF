<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserLog;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login with email (or username) + password.
     * If two-factor is on, the first call answers {two_factor_required: true};
     * the app then sends the same request again with the 6-digit "code".
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
            'remember' => 'boolean',
            'code' => 'nullable|string',
        ]);

        $user = User::where('email', $data['email'])->orWhere('username', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['These credentials do not match our records.']]);
        }
        if (! $user->active) {
            throw ValidationException::withMessages(['email' => ['This account is disabled.']]);
        }

        if ($user->two_factor_enabled) {
            if (empty($data['code'])) {
                return response()->json(['two_factor_required' => true]);
            }
            if (! Totp::verify((string) $user->two_factor_secret, $data['code'])) {
                throw ValidationException::withMessages(['code' => ['The two-factor code is not correct.']]);
            }
        }

        $expires = ($data['remember'] ?? false) ? now()->addDays(30) : now()->addHours(12);
        $token = $user->createToken('spa', ['*'], $expires)->plainTextToken;
        UserLog::record('login', $user->id);

        return response()->json(['token' => $token, 'user' => $this->payload($user)]);
    }

    public function me(Request $request)
    {
        return $this->payload($request->user());
    }

    public function logout(Request $request)
    {
        UserLog::record('logout', $request->user()->id);
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public static function payload(User $user): array
    {
        $user->load('title', 'position', 'department', 'roles:id,title');

        return array_merge($user->toArray(), ['permissions' => $user->permissionTitles()]);
    }
}
