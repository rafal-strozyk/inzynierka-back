<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        return UserResource::collection(User::query()->latest()->paginate($perPage));
    }

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

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'surname' => ['nullable', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', Rule::in(['admin', 'owner', 'tenant'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'birth_date' => ['nullable', 'date'],
            'pesel' => ['nullable', 'string', 'size:11', 'unique:users,pesel'],
        ]);

        $user = User::query()->create([
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
        ]);

        return response()->json(['user' => $user], 201);
    }

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
