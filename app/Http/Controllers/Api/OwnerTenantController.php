<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenantProperty;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class OwnerTenantController extends Controller
{
    /**
     * Lista tenantow.
     *
     * @group Owner
     * @authenticated
     *
     * @queryParam page int Numer strony. Example: 2
     * @queryParam per_page int Liczba rekordow na strone. Example: 10
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = User::query()->where('role', 'tenant');

        if ($actor?->role === 'owner') {
            $query->whereIn('id', function ($subQuery) use ($actor) {
                $subQuery->select('tenants_properties.tenant_id')
                    ->from('tenants_properties')
                    ->join('properties', 'tenants_properties.property_id', '=', 'properties.id')
                    ->where('properties.owner_id', $actor->id);
            });
        }

        return response()->json($query->latest()->paginate($perPage));
    }

    /**
     * Tworzenie tenanta.
     *
     * @group Owner
     * @authenticated
     *
     * @bodyParam name string required Imie i nazwisko. Example: Jan Kowalski
     * @bodyParam email string required Email uzytkownika. Example: user@example.com
     * @bodyParam password string required Haslo (min. 8 znakow). Example: secret123
     * @bodyParam password_confirmation string required Potwierdzenie hasla. Example: secret123
     * @bodyParam first_name string Imie. Example: Jan
     * @bodyParam last_name string Nazwisko. Example: Kowalski
     * @bodyParam phone string Numer telefonu. Example: +48 500 000 001
     * @bodyParam address_registered string Adres zameldowania. Example: ul. Glowna 1
     * @bodyParam city string Miasto. Example: Warszawa
     * @bodyParam birth_date date Data urodzenia (YYYY-MM-DD). Example: 1990-01-01
     * @bodyParam pesel string PESEL. Example: 90010112345
     * @bodyParam notes string Uwagi. Example: Nowy najemca.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
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
            'role' => 'tenant',
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
     * Edycja danych tenanta przez ownera.
     *
     * @group Owner
     * @authenticated
     *
     * @urlParam user int required ID tenanta. Example: 1
     * @bodyParam name string Imie i nazwisko. Example: Jan Kowalski
     * @bodyParam email string Email uzytkownika. Example: user@example.com
     * @bodyParam first_name string Imie. Example: Jan
     * @bodyParam last_name string Nazwisko. Example: Kowalski
     * @bodyParam phone string Numer telefonu. Example: +48 500 000 001
     * @bodyParam address_registered string Adres zameldowania. Example: ul. Glowna 1
     * @bodyParam city string Miasto. Example: Warszawa
     * @bodyParam birth_date date Data urodzenia (YYYY-MM-DD). Example: 1990-01-01
     * @bodyParam pesel string PESEL. Example: 90010112345
     * @bodyParam notes string Uwagi. Example: Aktualizacja danych.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $accessError = $this->ensureTenantAccess($request, $user);
        if ($accessError) {
            return $accessError;
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address_registered' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            'pesel' => ['nullable', 'string', 'max:11'],
            'notes' => ['nullable', 'string'],
        ]);

        $user->fill($validated)->save();

        return response()->json(['user' => $user]);
    }

    /**
     * Usuwanie tenanta.
     *
     * @group Owner
     * @authenticated
     *
     * @urlParam user int required ID tenanta. Example: 1
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $accessError = $this->ensureTenantAccess($request, $user);
        if ($accessError) {
            return $accessError;
        }

        $user->delete();

        return response()->json(['message' => 'Tenant deleted.']);
    }

    private function ensureTenantAccess(Request $request, User $user): ?JsonResponse
    {
        if ($user->role !== 'tenant') {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        $actor = $request->user();

        if ($actor?->role !== 'owner') {
            return null;
        }

        $hasTenant = TenantProperty::query()
            ->join('properties', 'tenants_properties.property_id', '=', 'properties.id')
            ->where('properties.owner_id', $actor->id)
            ->where('tenants_properties.tenant_id', $user->id)
            ->exists();

        if (!$hasTenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        return null;
    }
}
