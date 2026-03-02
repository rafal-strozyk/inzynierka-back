<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantAssignmentResource;
use App\Models\ContractTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantSelfController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function assignments(Request $request): JsonResponse
    {
        $user = $request->user();

        $assignments = ContractTenant::query()
            ->where('user_id', $user->id)
            ->with(['contract.property', 'user'])
            ->orderByDesc('joined_at')
            ->get();

        return response()->json(TenantAssignmentResource::collection($assignments));
    }
}
