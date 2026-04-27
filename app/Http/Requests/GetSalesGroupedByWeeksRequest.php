<?php

namespace App\Http\Requests;

use App\Models\Business;
use App\Services\BusinessAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;

class GetSalesGroupedByWeeksRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! Business::find($this->input('business_id'))) {
            return false;
        }

        $svc = app(BusinessAuthorizationService::class);
        $user = $this->user();

        if (! $svc->userCanAccessBusiness($user, $this->input('business_id'))) {
            return false;
        }

        if (filter_var($this->input('all'), FILTER_VALIDATE_BOOLEAN)) {
            return $svc->userHasFullBusinessScope($user, $this->input('business_id'));
        }

        return $svc->userCanAccessLocation($user, $this->input('business_id'), $this->input('location_id'));
    }

    public function rules(): array
    {
        return [
            'business_id' => 'required|exists:businesses,id',
            'location_id' => 'required|exists:locations,id',
            'groupParameter' => 'required|string',
            'all' => 'sometimes|boolean',
        ];
    }
}
