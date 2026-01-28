<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantAssignmentResource;
use App\Models\TenantProperty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantSelfController extends Controller
{
    /**
     * Moje dane.
     *
     * @group Tenant
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    /**
     * Moje przypisania do nieruchomosci/pokoi.
     *
     * @group Tenant
     */
    public function assignments(Request $request): JsonResponse
    {
        $user = $request->user();

        $assignments = TenantProperty::query()
            ->where('tenant_id', $user->id)
            ->with(['property', 'room'])
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get();

        return response()->json(TenantAssignmentResource::collection($assignments));
    }
}
