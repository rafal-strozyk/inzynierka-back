<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OwnerTenantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = User::query()->where('role', 'tenant');

        if ($actor?->role === 'owner') {
            $query->whereIn('id', function ($subQuery) use ($actor) {
                $subQuery->select('contract_tenants.user_id')
                    ->from('contract_tenants')
                    ->join('contracts', 'contract_tenants.contract_id', '=', 'contracts.id')
                    ->join('properties', 'contracts.property_id', '=', 'properties.id')
                    ->where('properties.owner_user_id', $actor->id);
            });
        }

        return response()->json($query->latest()->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'surname' => ['nullable', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'birth_date' => ['nullable', 'date'],
            'pesel' => ['nullable', 'string', 'size:11', 'unique:users,pesel'],
        ]);

        $user = User::query()->create([
            'role' => 'tenant',
            'username' => $validated['username'] ?? $this->generateUniqueUsername($validated['name'], $validated['surname'] ?? null),
            'name' => $validated['name'],
            'surname' => $validated['surname'] ?? '-',
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'pesel' => $validated['pesel'] ?? null,
        ]);

        return response()->json(['user' => $user], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $accessError = $this->ensureTenantAccess($request, $user);
        if ($accessError) {
            return $accessError;
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'surname' => ['nullable', 'string', 'max:120'],
            'username' => ['sometimes', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'birth_date' => ['nullable', 'date'],
            'pesel' => ['nullable', 'string', 'size:11', Rule::unique('users', 'pesel')->ignore($user->id)],
        ]);

        if (!array_key_exists('username', $validated) && array_key_exists('name', $validated)) {
            $validated['username'] = $this->generateUniqueUsername($validated['name'], $validated['surname'] ?? $user->surname, $user->id);
        }

        $user->fill($validated)->save();

        return response()->json(['user' => $user]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $accessError = $this->ensureTenantAccess($request, $user);
        if ($accessError) {
            return $accessError;
        }

        try {
            $user->delete();
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Tenant cannot be deleted because it is linked to active records.',
            ], 409);
        }

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

        $hasTenant = $user->contractTenants()
            ->join('contracts', 'contract_tenants.contract_id', '=', 'contracts.id')
            ->join('properties', 'contracts.property_id', '=', 'properties.id')
            ->where('properties.owner_user_id', $actor->id)
            ->exists();

        if (!$hasTenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        return null;
    }

    private function generateUniqueUsername(string $name, ?string $surname = null, ?int $ignoreId = null): string
    {
        $base = Str::lower(Str::slug(trim($name . ' ' . ($surname ?? '')), ''));
        if ($base === '') {
            $base = 'tenant';
        }

        $candidate = Str::substr($base, 0, 45);
        $suffix = 0;

        while (
            User::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('username', $candidate)
                ->exists()
        ) {
            $suffix++;
            $candidate = Str::substr($base, 0, 45 - strlen((string) $suffix)) . $suffix;
        }

        return $candidate;
    }
}
