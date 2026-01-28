<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    /**
     * Lista uzytkownikow.
     *
     * @group Admin
     *
     * @queryParam page int Numer strony. Example: 2
     * @queryParam per_page int Liczba rekordow na strone. Example: 10
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $users = User::query()->latest()->paginate($perPage);

        return response()->json($users);
    }

    /**
     * Tworzenie uzytkownika przez admina.
     *
     * @group Admin
     *
     * @bodyParam name string required Imie i nazwisko. Example: Jan Kowalski
     * @bodyParam email string required Email uzytkownika. Example: user@example.com
     * @bodyParam password string required Haslo (min. 8 znakow). Example: secret123
     * @bodyParam password_confirmation string required Potwierdzenie hasla. Example: secret123
     * @bodyParam role string Rola uzytkownika (admin/owner/tenant). Example: tenant
     * @bodyParam first_name string Imie. Example: Jan
     * @bodyParam last_name string Nazwisko. Example: Kowalski
     * @bodyParam phone string Numer telefonu. Example: +48 500 000 001
     * @bodyParam address_registered string Adres zameldowania. Example: ul. Glowna 1
     * @bodyParam city string Miasto. Example: Warszawa
     * @bodyParam birth_date date Data urodzenia (YYYY-MM-DD). Example: 1990-01-01
     * @bodyParam pesel string PESEL. Example: 90010112345
     * @bodyParam notes string Uwagi. Example: Nowy uzytkownik.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', Rule::in(['admin', 'owner', 'tenant'])],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address_registered' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            'pesel' => ['nullable', 'string', 'max:11'],
            'notes' => ['nullable', 'string'],
        ]);

        $passwordHash = Hash::make($validated['password']);

        $user = User::query()->create([
            'role' => $validated['role'] ?? 'tenant',
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

        return response()->json(['user' => $user], 201);
    }

    /**
     * Edycja uzytkownika przez admina.
     *
     * @group Admin
     *
     * @urlParam user int required ID uzytkownika. Example: 1
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'role' => ['sometimes', Rule::in(['admin', 'owner', 'tenant'])],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address_registered' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            'pesel' => ['nullable', 'string', 'max:11'],
            'notes' => ['nullable', 'string'],
        ]);

        $payload = $validated;

        if (array_key_exists('password', $validated)) {
            $passwordHash = Hash::make($validated['password']);
            $payload['password'] = $passwordHash;
            $payload['password_hash'] = $passwordHash;
        }

        $user->fill($payload)->save();

        return response()->json(['user' => $user]);
    }
}
