<?php

namespace App\Http\Controllers;

use App\Models\LoginSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const TOKEN_BYTES = 48;
    private const SESSION_DAYS = 7;

    /**
     * Logowanie
     *
     * Zwraca token sesji do użycia w nagłówku Authorization (Bearer).
     *
     * @group Autoryzacja
     * @unauthenticated
     *
     * @bodyParam email string required Email użytkownika. Example: user@example.com
     * @bodyParam password string required Hasło użytkownika. Example: secret123
     *
     * @response 200 {
     *  "token": "string",
     *  "token_type": "Bearer",
     *  "expires_at": "2026-01-11T10:00:00+00:00",
     *  "user": {
     *    "id": 1,
     *    "name": "Jan Kowalski",
     *    "email": "user@example.com",
     *    "created_at": "2026-01-11T10:00:00+00:00",
     *    "updated_at": "2026-01-11T10:00:00+00:00"
     *  }
     * }
     * @response 401 {
     *  "message": "Invalid credentials."
     * }
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $token = $this->generateUniqueToken();
        $expiresAt = Carbon::now()->addDays(self::SESSION_DAYS);

        $session = LoginSession::query()->create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'token' => $session->token,
            'token_type' => 'Bearer',
            'expires_at' => $session->expires_at->toIso8601String(),
            'user' => $user,
        ]);
    }

    /**
     * Wylogowanie
     *
     * Inwaliduje bieżący token sesji.
     *
     * @group Autoryzacja
     *
     * @response 200 {
     *  "message": "Logged out."
     * }
     * @response 401 {
     *  "message": "Missing token."
     * }
     */
    public function logout(Request $request): JsonResponse
    {
        $session = $request->attributes->get('login_session');

        if ($session instanceof LoginSession) {
            $session->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Bieżący użytkownik
     *
     * Zwraca dane zalogowanego użytkownika na podstawie tokenu.
     *
     * @group Autoryzacja
     *
     * @response 200 {
     *  "user": {
     *    "id": 1,
     *    "name": "Jan Kowalski",
     *    "email": "user@example.com",
     *    "created_at": "2026-01-11T10:00:00+00:00",
     *    "updated_at": "2026-01-11T10:00:00+00:00"
     *  }
     * }
     * @response 401 {
     *  "message": "Missing token."
     * }
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    private function generateUniqueToken(): string
    {
        do {
            $token = Str::random(self::TOKEN_BYTES);
        } while (LoginSession::query()->where('token', $token)->exists());

        return $token;
    }
}
