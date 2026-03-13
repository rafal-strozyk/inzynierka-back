<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Resources\TenantAssignmentResource;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantAssignmentController extends Controller
{
    /**
     * @group Assignments
     * @authenticated
     * @bodyParam tenant_id int required ID najemcy. Example: 12
     * @bodyParam property_id int required ID nieruchomości. Example: 5
     * @bodyParam contract_id int ID istniejącej umowy.
     * @bodyParam is_primary bool Czy najemca jest główny. Example: false
     * @response 201
     * {
     *   "assignment": {
     *     "id": 8,
     *     "contract_id": 3,
     *     "users_username": "jan"
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'tenant')],
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $actor = $request->user();
        $property = Property::query()->findOrFail($validated['property_id']);

        if ($actor?->role === 'owner' && $property->owner_user_id !== $actor->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $contract = null;
        if (!empty($validated['contract_id'])) {
            $contract = Contract::query()->findOrFail($validated['contract_id']);
            if ($contract->property_id !== $property->id) {
                return response()->json(['message' => 'Contract does not belong to the property.'], 422);
            }
        }

        if (!$contract) {
            $contract = Contract::query()
                ->where('property_id', $property->id)
                ->whereIn('status', ['draft', 'active'])
                ->latest('id')
                ->first();
        }

        if (!$contract) {
            $contract = Contract::query()->create([
                'property_id' => $property->id,
                'properties_name' => $property->name,
                'contract_number' => 'CTR-' . strtoupper(Str::random(10)),
                'start_date' => now()->toDateString(),
                'monthly_rent' => $property->rent_cost,
                'deposit' => $property->rent_cost,
                'status' => 'draft',
                'payment_method' => 'bank_transfer',
            ]);
        }

        $assignment = ContractTenant::query()->firstOrCreate(
            [
                'contract_id' => $contract->id,
                'user_id' => $validated['tenant_id'],
            ],
            [
                'users_username' => User::query()->findOrFail($validated['tenant_id'])->username,
                'is_primary' => (bool) ($validated['is_primary'] ?? false),
                'contracts_contract_number' => $contract->contract_number,
            ]
        );

        if (!$assignment->wasRecentlyCreated) {
            return response()->json(['message' => 'Assignment already exists.'], 409);
        }

        return response()->json([
            'assignment' => new TenantAssignmentResource($assignment->load(['contract.property', 'user'])),
        ], 201);
    }

    /**
     * @group Assignments
     * @authenticated
     * @pathParam assignment int ID przypisania (contract_participants).
     * @response 200
     * {
     *   "message": "Assignment deleted.",
     *   "assignment": {}
     * }
     */
    public function destroy(Request $request, int $assignment): JsonResponse
    {
        $assignmentModel = ContractTenant::query()->with(['contract.property', 'user'])->findOrFail($assignment);

        $actor = $request->user();
        if ($actor?->role === 'owner' && $assignmentModel->contract?->property?->owner_user_id !== $actor->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $assignmentModel->delete();

        return response()->json([
            'message' => 'Assignment deleted.',
            'assignment' => new TenantAssignmentResource($assignmentModel),
        ]);
    }
}
