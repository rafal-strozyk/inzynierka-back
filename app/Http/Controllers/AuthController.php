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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    private const TOKEN_BYTES = 48;
    private const RESET_TOKEN_BYTES = 64;
    private const SESSION_DAYS = 7;

    /**
     * Rejestracja nowego użytkownika.
     *
     * @group Auth
     * @authenticated
     *
     * @bodyParam name string required Imię. Example: Jan
     * @bodyParam surname string Nazwisko. Example: Kowalski
     * @bodyParam username string Unikalna nazwa użytkownika (opcjonalnie). Example: jan.kowalski
     * @bodyParam email string required Email użytkownika. Example: jan@example.com
     * @bodyParam password string required Hasło (min. 8 znaków). Example: haslo1234
     * @bodyParam password_confirmation string required Potwierdzenie hasła (musi być identyczne jak `password`). Example: haslo1234
     * @bodyParam role string Rola nowego użytkownika: `owner` lub `tenant`. Example: tenant
     * @bodyParam phone string Telefon. Example: +48500100100
     * @bodyParam address string Adres. Example: ul. Testowa 10
     * @bodyParam postal_code string Kod pocztowy. Example: 00-001
     * @bodyParam birth_date date Data urodzenia (YYYY-MM-DD). Example: 1998-04-12
     * @bodyParam pesel string PESEL (11 cyfr). Example: 98041212345
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'surname' => ['nullable', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', Rule::in(['owner', 'tenant'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'birth_date' => ['nullable', 'date'],
            'pesel' => ['nullable', 'string', 'size:11', 'unique:users,pesel'],
        ]);

        $userPayload = [
            'username' => $validated['username'] ?? $this->generateUniqueUsername($validated['name'], $validated['surname'] ?? null),
            'role' => $validated['role'] ?? 'tenant',
            'name' => $validated['name'],
            'surname' => $validated['surname'] ?? '-',
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'pesel' => $validated['pesel'] ?? null,
        ];

        // Fallback dla środowisk, gdzie migracje users nie zostały jeszcze dograne.
        $existingColumns = array_flip(Schema::getColumnListing('users'));
        $userPayload = array_intersect_key($userPayload, $existingColumns);

        $user = User::query()->create($userPayload);

        return response()->json(['user' => $user], 201);
    }

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

    public function logout(Request $request): JsonResponse
    {
        $session = $request->attributes->get('login_session');

        if ($session instanceof LoginSession) {
            $session->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

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

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        $session = $request->attributes->get('login_session');
        $sessions = LoginSession::query()->where('user_id', $user->id);

        if ($session instanceof LoginSession) {
            $sessions->where('id', '!=', $session->id);
        }

        $sessions->delete();

        return response()->json(['message' => 'Password updated.']);
    }

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

        return response()->json(['message' => 'If the email exists, a reset link was sent.']);
    }

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

        if (!$record || $this->isResetTokenExpired($record->created_at) || !Hash::check($validated['token'], $record->token)) {
            return response()->json(['message' => 'Invalid token or email.'], 422);
        }

        $user = User::query()->where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid token or email.'], 422);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        $this->deletePasswordResetToken($validated['email']);
        LoginSession::query()->where('user_id', $user->id)->delete();

        return response()->json(['message' => 'Password reset.']);
    }

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

        $user->forceFill([
            'password' => Hash::make($validated['password']),
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

    private function generateUniqueUsername(string $name, ?string $surname = null): string
    {
        $base = Str::lower(Str::slug(trim($name . ' ' . ($surname ?? '')), ''));
        if ($base === '') {
            $base = 'user';
        }

        $candidate = Str::substr($base, 0, 45);
        $suffix = 0;

        while (User::query()->where('username', $candidate)->exists()) {
            $suffix++;
            $candidate = Str::substr($base, 0, 45 - strlen((string) $suffix)) . $suffix;
        }

        return $candidate;
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
