<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OwnerTenantController extends Controller
{
    /**
     * Edycja danych tenanta przez ownera.
     *
     * @group Owner
     *
     * @urlParam user int required ID tenanta. Example: 1
     */
    public function update(Request $request, User $user): JsonResponse
    {
        if ($user->role !== 'tenant') {
            return response()->json(['message' => 'Tenant not found.'], 404);
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
}
