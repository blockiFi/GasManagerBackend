<?php

namespace App\Http\Controllers\Concerns;

use App\Services\BusinessAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait AuthorizesTenantRequests
{
    protected function businessAuth(): BusinessAuthorizationService
    {
        return app(BusinessAuthorizationService::class);
    }

    protected function denyUnlessCanAccessBusiness(Request $request): ?JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $request->filled('business_id')) {
            return null;
        }

        if (! $this->businessAuth()->userCanAccessBusiness($user, $request->input('business_id'))) {
            return response()->json([
                'code' => 403,
                'errors' => ['You do not have access to this business.'],
            ], 403);
        }

        return null;
    }

    protected function denyUnlessCanAccessBusinessAndLocation(Request $request): ?JsonResponse
    {
        if ($denied = $this->denyUnlessCanAccessBusiness($request)) {
            return $denied;
        }

        $user = Auth::user();
        if (! $user || ! $request->filled('business_id') || ! $request->filled('location_id')) {
            return null;
        }

        if (! $this->businessAuth()->userCanAccessLocation($user, $request->input('business_id'), $request->input('location_id'))) {
            return response()->json([
                'code' => 403,
                'errors' => ['You do not have access to this location.'],
            ], 403);
        }

        return null;
    }

    protected function denyUnlessFullBusinessScope(Request $request): ?JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $request->filled('business_id')) {
            return null;
        }

        if (! $this->businessAuth()->userHasFullBusinessScope($user, $request->input('business_id'))) {
            return response()->json([
                'code' => 403,
                'errors' => ['You do not have permission for this business-wide action.'],
            ], 403);
        }

        return null;
    }

    protected function denyUnlessCanAccessSupplyForUser($supplyId): ?JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        if (! $this->businessAuth()->userCanAccessSupply($user, $supplyId)) {
            return response()->json([
                'code' => 403,
                'errors' => ['You do not have access to this supply.'],
            ], 403);
        }

        return null;
    }

    protected function denyUnlessCanAccessSaleForUser($saleId): ?JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        if (! $this->businessAuth()->userCanAccessSale($user, $saleId)) {
            return response()->json([
                'code' => 403,
                'errors' => ['You do not have access to this sale.'],
            ], 403);
        }

        return null;
    }
}
