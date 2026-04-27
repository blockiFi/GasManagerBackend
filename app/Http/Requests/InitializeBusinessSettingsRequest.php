<?php

namespace App\Http\Requests;

use App\Models\Business;
use App\Services\BusinessAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;

class InitializeBusinessSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! Business::find($this->input('business_id'))) {
            return false;
        }

        return app(BusinessAuthorizationService::class)
            ->userCanAccessBusiness($this->user(), $this->input('business_id'));
    }

    public function rules(): array
    {
        return [
            'business_id' => 'required|exists:businesses,id',
        ];
    }
}
