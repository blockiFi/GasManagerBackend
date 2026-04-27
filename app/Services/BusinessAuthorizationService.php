<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Business_User;
use App\Models\Location;
use App\Models\Sale;
use App\Models\Supply;
use App\Models\User;

class BusinessAuthorizationService
{
    public function userCanAccessBusiness(User $user, mixed $businessId): bool
    {
        if ($businessId === null || $businessId === '') {
            return false;
        }

        $business = Business::find($businessId);
        if (! $business) {
            return false;
        }

        if ($this->isOwner($user, $business)) {
            return true;
        }

        if ($this->hasBusinessMembership($user, $businessId)) {
            return true;
        }

        return $this->managesAnyLocationInBusiness($user, $businessId);
    }

    /**
     * Owner or explicit business membership (not location-manager-only).
     */
    public function userHasFullBusinessScope(User $user, mixed $businessId): bool
    {
        $business = Business::find($businessId);
        if (! $business) {
            return false;
        }

        if ($this->isOwner($user, $business)) {
            return true;
        }

        return $this->hasBusinessMembership($user, $businessId);
    }

    public function userCanAccessLocation(User $user, mixed $businessId, mixed $locationId): bool
    {
        if (! Location::where('id', $locationId)->where('business_id', $businessId)->exists()) {
            return false;
        }

        $business = Business::find($businessId);
        if (! $business) {
            return false;
        }

        if ($this->isOwner($user, $business)) {
            return true;
        }

        if ($this->hasBusinessMembership($user, $businessId)) {
            return true;
        }

        $location = Location::where('id', $locationId)->where('business_id', $businessId)->first();

        return $location && (string) $location->manager_id === (string) $user->id;
    }

    public function userCanAccessSupply(User $user, mixed $supplyId): bool
    {
        $supply = Supply::find($supplyId);
        if (! $supply) {
            return false;
        }

        return $this->userCanAccessLocation($user, $supply->business_id, $supply->location_id);
    }

    /**
     * @return list<int|string>|null null = all locations in business; non-null = restricted ids
     */
    public function userAccessibleLocationIds(User $user, mixed $businessId): ?array
    {
        if ($this->userHasFullBusinessScope($user, $businessId)) {
            return null;
        }

        return Location::query()
            ->where('business_id', $businessId)
            ->where('manager_id', (string) $user->id)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function userCanAccessSale(User $user, mixed $saleId): bool
    {
        $sale = Sale::find($saleId);
        if (! $sale) {
            return false;
        }

        return $this->userCanAccessLocation($user, $sale->business_id, $sale->location_id);
    }

    protected function isOwner(User $user, Business $business): bool
    {
        return (string) $business->owner_id === (string) $user->id;
    }

    protected function hasBusinessMembership(User $user, mixed $businessId): bool
    {
        return Business_User::where('user_id', $user->id)->where('business_id', $businessId)->exists();
    }

    protected function managesAnyLocationInBusiness(User $user, mixed $businessId): bool
    {
        return Location::where('business_id', $businessId)
            ->where('manager_id', (string) $user->id)
            ->exists();
    }
}
