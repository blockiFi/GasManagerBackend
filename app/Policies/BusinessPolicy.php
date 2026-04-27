<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;
use App\Services\BusinessAuthorizationService;

class BusinessPolicy
{
    public function __construct(
        protected BusinessAuthorizationService $businessAuthorization
    ) {}

    public function access(User $user, Business $business): bool
    {
        return $this->businessAuthorization->userCanAccessBusiness($user, $business->id);
    }

    public function fullScope(User $user, Business $business): bool
    {
        return $this->businessAuthorization->userHasFullBusinessScope($user, $business->id);
    }
}
