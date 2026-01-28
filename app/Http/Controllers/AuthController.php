<?php

namespace App\Http\Controllers;

use App\Models\LoginSession;
use App\Models\User;
use App\Notifications\PasswordResetLinkNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    private const TOKEN_BYTES = 48;
    private const RESET_TOKEN_BYTES = 64;
    private const SESSION_DAYS = 7;

    /**
     * Rejestracja
     *
     * Tworzy konto użytkownika (domyślnie rola tenant).
     * Wymaga roli owner lub admin.
     *
     * @group Autoryzacja
     * @authenticated
     *
     * @bodyParam name string required Imię i nazwisko. Example: Jan Kowalski
     * @bodyParam email string required Email użytkownika. Example: user@example.com
     * @bodyParam password string required Hasło (min. 8 znaków). Example: secret123
     * @bodyParam password_confirmation string required Potwierdzenie hasła. Example: secret123
     * @bodyParam role string Rola użytkownika (owner/tenant). Example: tenant
     * @bodyParam first_name string Imię. Example: Jan
     * @bodyParam last_name string Nazwisko. Example: Kowalski
     * @bodyParam phone string Numer telefonu. Example: +48 500 000 001
     * @bodyParam address_registered string Adres zameldowania. Example: ul. Główna 1
     * @bodyParam city string Miasto. Example: Warszawa
     * @bodyParam birth_date date Data urodzenia (YYYY-MM-DD). Example: 1990-01-01
     * @bodyParam pesel string PESEL. Example: 90010112345
     * @bodyParam notes string Uwagi. Example: Nowy użytkownik.
     *
     * @response 201 {
     *  "user": {
     *    "id": 1,
     *    "role": "tenant",
     *    "name": "Jan Kowalski",
     *    "email": "user@example.com",
     *    "first_name": "Jan",
     *    "last_name": "Kowalski",
     *    "created_at": "2026-01-11T10:00:00+00:00",
     *    "updated_at": "2026-01-11T10:00:00+00:00"
     *  }
     * }
     * @response 403 {
     *  "message": "Forbidden."
     * }
     * @response 422 {
     *  "message": "The email has already been taken.",
     *  "errors": {
     *    "email": ["The email has already been taken."]
     *  }
     * }
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', Rule::in(['owner', 'tenant'])],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address_registered' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            'pesel' => ['nullable', 'string', 'max:11'],
            'notes' => ['nullable', 'string'],
        ]);

        $role = $validated['role'] ?? 'tenant';
        $passwordHash = Hash::make($validated['password']);

        $user = User::query()->create([
            'role' => $role,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $passwordHash,
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address_registered' => $validated['address_registered'] ?? null,
            'city' => $validated['city'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'pesel' => $validated['pesel'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'password_hash' => $passwordHash,
        ]);

        return response()->json([
            'user' => $user,
        ], 201);
    }

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

    /**
     * Zmiana hasla zalogowanego uzytkownika
     *
     * @group Autoryzacja
     *
     * @bodyParam current_password string required Obecne haslo. Example: secret123
     * @bodyParam password string required Nowe haslo (min. 8 znakow). Example: secret123
     * @bodyParam password_confirmation string required Potwierdzenie hasla. Example: secret123
     *
     * @response 200 {
     *  "message": "Password updated."
     * }
     * @response 422 {
     *  "message": "Invalid current password."
     * }
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!$user || !Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Invalid current password.'], 422);
        }

        $passwordHash = Hash::make($validated['password']);
        $user->forceFill([
            'password' => $passwordHash,
            'password_hash' => $passwordHash,
        ])->save();

        $session = $request->attributes->get('login_session');
        $sessions = LoginSession::query()->where('user_id', $user->id);

        if ($session instanceof LoginSession) {
            $sessions->where('id', '!=', $session->id);
        }

        $sessions->delete();

        return response()->json(['message' => 'Password updated.']);
    }

    /**
     * Reset hasla (zapomniane haslo)
     *
     * Wysyla link z tokenem resetu na email uzytkownika.
     *
     * @group Autoryzacja
     * @unauthenticated
     *
     * @bodyParam email string required Email uzytkownika. Example: user@example.com
     *
     * @response 200 {
     *  "message": "If the email exists, a reset link was sent."
     * }
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user) {
            $token = Str::random(self::RESET_TOKEN_BYTES);
            $this->storePasswordResetToken($user->email, $token);

            $resetUrl = $this->buildPasswordResetUrl($user->email, $token);
            $user->notify(new PasswordResetLinkNotification($resetUrl));
        }

        return response()->json([
            'message' => 'If the email exists, a reset link was sent.',
        ]);
    }

    /**
     * Ustaw nowe haslo
     *
     * Zmienia haslo uzytkownika na podstawie tokenu.
     *
     * @group Autoryzacja
     * @unauthenticated
     *
     * @bodyParam email string required Email uzytkownika. Example: user@example.com
     * @bodyParam token string required Token resetu. Example: abc123
     * @bodyParam password string required Haslo (min. 8 znakow). Example: secret123
     * @bodyParam password_confirmation string required Potwierdzenie hasla. Example: secret123
     *
     * @response 200 {
     *  "message": "Password reset."
     * }
     * @response 422 {
     *  "message": "Invalid token or email."
     * }
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = DB::table($this->passwordResetTable())
            ->where('email', $validated['email'])
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Invalid token or email.'], 422);
        }

        if ($this->isResetTokenExpired($record->created_at)) {
            $this->deletePasswordResetToken($validated['email']);
            return response()->json(['message' => 'Invalid token or email.'], 422);
        }

        if (!Hash::check($validated['token'], $record->token)) {
            return response()->json(['message' => 'Invalid token or email.'], 422);
        }

        $user = User::query()->where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid token or email.'], 422);
        }

        $passwordHash = Hash::make($validated['password']);
        $user->forceFill([
            'password' => $passwordHash,
            'password_hash' => $passwordHash,
        ])->save();

        $this->deletePasswordResetToken($validated['email']);
        LoginSession::query()->where('user_id', $user->id)->delete();

        return response()->json(['message' => 'Password reset.']);
    }

    /**
     * Reset hasla przez admina
     *
     * Admin moze ustawic haslo bez maila albo wyslac link do resetu.
     *
     * @group Autoryzacja
     *
     * @urlParam user int required ID uzytkownika. Example: 1
     * @bodyParam send_email boolean Czy wyslac link resetu. Example: true
     * @bodyParam password string Haslo (min. 8 znakow). Example: secret123
     * @bodyParam password_confirmation string Potwierdzenie hasla. Example: secret123
     *
     * @response 200 {
     *  "message": "Password updated."
     * }
     * @response 200 {
     *  "message": "Reset link sent."
     * }
     */
    public function adminResetPassword(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'send_email' => ['sometimes', 'boolean'],
            'password' => ['required_unless:send_email,true', 'string', 'min:8', 'confirmed'],
        ]);

        $sendEmail = (bool) ($validated['send_email'] ?? false);

        if ($sendEmail) {
            $token = Str::random(self::RESET_TOKEN_BYTES);
            $this->storePasswordResetToken($user->email, $token);

            $resetUrl = $this->buildPasswordResetUrl($user->email, $token);
            $user->notify(new PasswordResetLinkNotification($resetUrl, true));

            return response()->json(['message' => 'Reset link sent.']);
        }

        $passwordHash = Hash::make($validated['password']);
        $user->forceFill([
            'password' => $passwordHash,
            'password_hash' => $passwordHash,
        ])->save();

        $this->deletePasswordResetToken($user->email);
        LoginSession::query()->where('user_id', $user->id)->delete();

        return response()->json(['message' => 'Password updated.']);
    }

    private function generateUniqueToken(): string
    {
        do {
            $token = Str::random(self::TOKEN_BYTES);
        } while (LoginSession::query()->where('token', $token)->exists());

        return $token;
    }

    private function passwordResetTable(): string
    {
        return (string) config('auth.passwords.users.table', 'password_reset_tokens');
    }

    private function storePasswordResetToken(string $email, string $token): void
    {
        DB::table($this->passwordResetTable())->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($token),
                'created_at' => Carbon::now(),
            ]
        );
    }

    private function deletePasswordResetToken(string $email): void
    {
        DB::table($this->passwordResetTable())->where('email', $email)->delete();
    }

    private function isResetTokenExpired(?string $createdAt): bool
    {
        if (!$createdAt) {
            return true;
        }

        $expiresInMinutes = (int) config('auth.passwords.users.expire', 60);
        return Carbon::parse($createdAt)->addMinutes($expiresInMinutes)->isPast();
    }

    private function buildPasswordResetUrl(string $email, string $token): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        return $baseUrl . '/reset-password?email=' . urlencode($email) . '&token=' . urlencode($token);
    }
}
