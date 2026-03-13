<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class OwnerTenantController extends Controller
{
    /**
     * @group Owner Tenants
     * Lista najemców.
     * - owner: tylko przypisani do niego
     * - admin: wszyscy lub po filtrze owner_user_id
     * @authenticated
     * @queryParam per_page int Ile rekordów na stronę. Example: 10
     * @queryParam owner_user_id int (admin only) Filtrowanie po właścicielu. Example: 2
     * @response
     * {
     *   "data": [
     *     {
     *       "id": 12,
     *       "name": "Jan",
     *       "surname": "Kowalski",
     *       "email": "jan@example.com",
     *       "role": "tenant"
     *     }
     *   ],
     *   "current_page": 1
     * }
     */
    public function index(Request $request)
    {
        $actor = $request->user();
        if (!$actor || !in_array($actor->role, ['admin', 'owner'], true)) {
            abort(403, 'Forbidden');
        }
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $filters = $request->validate([
            'owner_user_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'owner')],
        ]);

        $query = User::query()->where('role', 'tenant');

        if ($actor?->role === 'owner') {
            $this->applyOwnerTenantScope($query, $actor->id);
        } elseif (!empty($filters['owner_user_id'])) {
            $this->applyOwnerTenantScope($query, (int) $filters['owner_user_id']);
        }

        return TenantResource::collection($query->latest()->paginate($perPage));
    }

    /**
     * @group Owner Tenants
     * Szczegóły pojedynczego najemcy.
     * @authenticated
     * @pathParam user int ID najemcy.
     */
    public function show(Request $request, User $user)
    {
        $accessError = $this->ensureTenantAccess($request, $user);
        if ($accessError) {
            return $accessError;
        }

        return new TenantResource($user);
    }

    /**
     * @group Owner Tenants
     * @authenticated
     * @bodyParam name string required Imię. Example: Jan
     * @bodyParam surname string Nazwisko. Example: Kowalski
     * @bodyParam email string required Email. Example: jan@example.com
     * @bodyParam password string required Hasło (min. 8). Example: haslo1234
     * @bodyParam password_confirmation string required Potwierdzenie hasła. Example: haslo1234
     * @bodyParam assigned_to int ID właściciela (admin only).
     * @bodyParam username string Login (opcjonalny).
     * @bodyParam phone string Telefon.
     * @bodyParam postal_code string Kod pocztowy.
     * @response 201
     * {
     *   "user": {
     *     "id": 12,
     *     "name": "Jan",
     *     "email": "jan@example.com",
     *     "role": "tenant"
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (!$actor || !in_array($actor->role, ['owner', 'admin'], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $isAdmin = $actor->role === 'admin';
        $hasAssignedTo = Schema::hasColumn('users', 'assigned_to');
        $assignedToRules = $isAdmin && $hasAssignedTo
            ? ['required', Rule::exists('users', 'id')->where('role', 'owner')]
            : ['nullable', 'prohibited'];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'surname' => ['nullable', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'assigned_to' => $assignedToRules,
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'birth_date' => ['nullable', 'date'],
            'pesel' => ['nullable', 'string', 'size:11', 'unique:users,pesel'],
        ]);

        $assignedOwnerId = $isAdmin
            ? ($validated['assigned_to'] ?? null)
            : $actor->id;

        $userData = [
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
        ];

        if ($hasAssignedTo) {
            $userData['assigned_to'] = $assignedOwnerId;
        }

        $user = User::query()->create($userData);

        return response()->json(['user' => $user], 201);
    }

    /**
     * @group Owner Tenants
     * @authenticated
     * Aktualizacja danych najemcy.
     * @pathParam user int ID najemcy.
     * @bodyParam name string Imię.
     * @bodyParam surname string Nazwisko.
     * @bodyParam email string Email.
     * @bodyParam username string Login.
     * @bodyParam phone string Telefon.
     * @bodyParam postal_code string Kod pocztowy.
     * @bodyParam assigned_to int ID właściciela (admin only).
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $accessError = $this->ensureTenantAccess($request, $user);
        if ($accessError) {
            return $accessError;
        }

        $hasAssignedTo = Schema::hasColumn('users', 'assigned_to');
        $isAdmin = $request->user()?->role === 'admin';

        $assignedToRule = $hasAssignedTo
            ? ($isAdmin ? ['sometimes', Rule::exists('users', 'id')->where('role', 'owner')] : ['prohibited'])
            : ['sometimes', 'nullable'];

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'surname' => ['nullable', 'string', 'max:120'],
            'username' => ['sometimes', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'assigned_to' => $assignedToRule,
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'birth_date' => ['nullable', 'date'],
            'pesel' => ['nullable', 'string', 'size:11', Rule::unique('users', 'pesel')->ignore($user->id)],
        ]);

        if (!$hasAssignedTo) {
            unset($validated['assigned_to']);
        }

        if (!array_key_exists('username', $validated) && array_key_exists('name', $validated)) {
            $validated['username'] = $this->generateUniqueUsername($validated['name'], $validated['surname'] ?? $user->surname, $user->id);
        }

        $user->fill($validated)->save();

        return response()->json(['user' => $user]);
    }

    /**
     * @group Owner Tenants
     * @authenticated
     * @pathParam user int ID najemcy.
     * @response 200
     * {"message":"Tenant deleted."}
     */
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

        if (Schema::hasColumn('users', 'assigned_to') && $user->assigned_to !== $actor->id) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        return null;
    }

    private function applyOwnerTenantScope($query, int $ownerId): void
    {
        if (!Schema::hasColumn('users', 'assigned_to')) {
            $query->whereHas('contractTenants.contract', function ($q) use ($ownerId) {
                $q->whereHas('property', function ($propertyQuery) use ($ownerId) {
                    $propertyQuery->where('owner_user_id', $ownerId);
                });
            });
            return;
        }

        $query->where('assigned_to', $ownerId);
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
