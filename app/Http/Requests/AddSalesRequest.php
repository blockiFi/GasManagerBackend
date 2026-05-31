<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddSalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = $this->input('business_id');
        $locationId = $this->input('location_id');

        return [
            // IDs may be sent as JSON numbers; do not use `string` or clients get "must be a string".
            'business_id' => ['required', Rule::exists('businesses', 'id')],
            'location_id' => [
                'required',
                Rule::exists('locations', 'id')->where('business_id', $businessId),
            ],
            'dispenser_id' => [
                'required',
                Rule::exists('dispensers', 'id')
                    ->where('business_id', $businessId)
                    ->where('location_id', $locationId),
            ],
            'opening_sales' => ['required', 'numeric'],
            'closing_sales' => ['required', 'numeric'],
            'opening_kg' => ['required', 'numeric'],
            'closing_kg' => ['required', 'numeric'],
            'sales_date' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location_id.exists' => 'The location does not exist for this business.',
            'dispenser_id.exists' => 'The dispenser does not exist for this business and location.',
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $response = response()->json([
            'code' => 400,
            'errors' => $validator->errors()->all(),
        ], 400);

        throw new \Illuminate\Http\Exceptions\HttpResponseException($response);
    }
}
