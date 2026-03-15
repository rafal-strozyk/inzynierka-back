<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    /**
     * @group Admin Users
     *
     * Lista użytkowników.
     *
     * @authenticated
     * @queryParam per_page int Ilość elementów na stronę. Example: 10
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        return UserResource::collection(User::query()->latest()->paginate($perPage));
    }

    /**
     * @group Admin Users
     * @authenticated
     *
     * Lista właścicieli.
     *
     * @queryParam per_page int Ilość elementów na stronę. Example: 10
     */
    public function owners(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        return UserResource::collection(
            User::query()
                ->where('role', 'owner')
                ->latest()
                ->paginate($perPage)
        );
    }

    /**
     * @group Admin Users
     * @authenticated
     *
     * Szczegóły użytkownika.
     *
     * @pathParam user int ID użytkownika.
     * @response
     * {
     *   "id": 3,
     *   "name": "Jan",
     *   "surname": "Kowalski",
     *   "email": "jan@example.com",
     *   "role": "tenant"
     * }
     */
    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    /**
     * @group Admin Users
     * @authenticated
     *
     * Tworzy nowego użytkownika.
     *
     * @bodyParam name string required Imię. Example: Jan
     * @bodyParam surname string Nazwisko. Example: Kowalski
     * @bodyParam username string Login. Example: jan.kowalski
     * @bodyParam email string required Email. Example: jan@example.com
     * @bodyParam password string required Min. 8 znaków. Example: haslo1234
     * @bodyParam role string Rola (`admin`, `owner`, `tenant`). Example: tenant
     * @bodyParam phone string Telefon. Example: +48111111111
     * @bodyParam address string Adres. Example: ul. Testowa 10
     * @bodyParam postal_code string Kod pocztowy. Example: 00-001
     * @bodyParam birth_date date Data urodzenia. Example: 1990-01-01
     * @bodyParam pesel string PESEL (11 cyfr). Example: 90010112345
     */
    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (!$actor || $actor->role !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $hasAssignedTo = Schema::hasColumn('users', 'assigned_to');
        $role = $request->input('role', 'tenant');
        $assignedToRules = $role === 'tenant' && $hasAssignedTo
            ? ['nullable', Rule::exists('users', 'id')->where('role', 'owner')]
            : ['prohibited'];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'surname' => ['nullable', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', Rule::in(['admin', 'owner', 'tenant'])],
            'assigned_to' => $assignedToRules,
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'birth_date' => ['nullable', 'date'],
            'pesel' => ['nullable', 'string', 'size:11', 'unique:users,pesel'],
        ]);

        $userData = [
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

        if ($hasAssignedTo && ($validated['role'] ?? 'tenant') === 'tenant') {
            $userData['assigned_to'] = $validated['assigned_to'] ?? null;
        }

        $userColumns = array_flip(Schema::getColumnListing('users'));
        $userData = array_intersect_key($userData, $userColumns);

        try {
            $user = User::query()->create($userData);
        } catch (QueryException $e) {
            $sqlState = (string) ($e->errorInfo[0] ?? '');
            $driverCode = (string) ($e->errorInfo[1] ?? '');
            $message = mb_strtolower($e->getMessage());

            if (
                $sqlState === '23000' && $driverCode === '1062'
                || $sqlState === '23505'
                || str_contains($message, 'duplicate entry')
            ) {
                return response()->json([
                    'message' => 'User data already exists.',
                    'errors' => ['email' => ['Email/username/PESEL already exists.']],
                ], 409);
            }

            if (
                $sqlState === '23000' && $driverCode === '1452'
                || $sqlState === '23503'
                || str_contains($message, 'foreign key constraint fails')
                || str_contains($message, 'foreign key constraint failed')
            ) {
                return response()->json([
                    'message' => 'Invalid owner assignment.',
                    'errors' => ['assigned_to' => ['The selected owner does not exist.']],
                ], 422);
            }

            return response()->json([
                'message' => 'Could not create user.',
            ], 409);
        }

        return response()->json(['user' => $user], 201);
    }

    /**
     * @group Admin Users
     * @authenticated
     *
     * Aktualizuje użytkownika.
     *
     * @pathParam user int ID użytkownika.
     * @bodyParam name string Imię. Example: Jan
     * @bodyParam surname string Nazwisko. Example: Kowalski
     * @bodyParam username string Login. Example: jan.kowalski
     * @bodyParam email string Email. Example: jan@example.com
     * @bodyParam password string Min. 8 znaków. Example: haslo1234
     * @bodyParam role string Rola (`admin`, `owner`, `tenant`). Example: owner
     * @bodyParam phone string Telefon. Example: +48111111111
     * @bodyParam address string Adres. Example: ul. Testowa 10
     * @bodyParam postal_code string Kod pocztowy. Example: 00-001
     * @bodyParam birth_date date Data urodzenia. Example: 1990-01-01
     * @bodyParam pesel string PESEL (11 cyfr). Example: 90010112345
     * @response
     * {
     *   "user": {
     *     "id": 3,
     *     "email": "jan@example.com",
     *     "role": "tenant"
     *   }
     * }
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'surname' => ['nullable', 'string', 'max:120'],
            'username' => ['sometimes', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'role' => ['sometimes', Rule::in(['admin', 'owner', 'tenant'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'birth_date' => ['nullable', 'date'],
            'pesel' => ['nullable', 'string', 'size:11', Rule::unique('users', 'pesel')->ignore($user->id)],
        ]);

        if (array_key_exists('password', $validated)) {
            $validated['password'] = Hash::make($validated['password']);
        }

        if (!array_key_exists('username', $validated) && array_key_exists('name', $validated)) {
            $validated['username'] = $this->generateUniqueUsername($validated['name'], $validated['surname'] ?? $user->surname, $user->id);
        }

        $user->fill($validated)->save();

        return response()->json(['user' => $user]);
    }

    private function generateUniqueUsername(string $name, ?string $surname = null, ?int $ignoreId = null): string
    {
        $base = Str::lower(Str::slug(trim($name . ' ' . ($surname ?? '')), ''));
        if ($base === '') {
            $base = 'user';
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
