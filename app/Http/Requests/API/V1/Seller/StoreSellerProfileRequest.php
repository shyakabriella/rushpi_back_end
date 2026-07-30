<?php

declare(strict_types=1);

namespace App\Http\Requests\API\V1\Seller;

use Illuminate\Foundation\Http\FormRequest;

class StoreSellerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'legal_business_name' => [
                'required',
                'string',
                'max:255',
            ],

            'trading_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'registration_number' => [
                'nullable',
                'string',
                'max:100',
                'unique:seller_profiles,registration_number',
            ],

            'tax_identification_number' => [
                'nullable',
                'string',
                'max:100',
                'unique:seller_profiles,tax_identification_number',
            ],

            'business_email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'business_phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'website' => [
                'nullable',
                'url',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'address' => [
                'nullable',
                'array',
            ],

            'address.country' => [
                'required_with:address',
                'string',
                'max:100',
            ],

            'address.province' => [
                'nullable',
                'string',
                'max:100',
            ],

            'address.district' => [
                'nullable',
                'string',
                'max:100',
            ],

            'address.sector' => [
                'nullable',
                'string',
                'max:100',
            ],

            'address.cell' => [
                'nullable',
                'string',
                'max:100',
            ],

            'address.village' => [
                'nullable',
                'string',
                'max:100',
            ],

            'address.address_line' => [
                'required_with:address',
                'string',
                'max:255',
            ],

            'address.postal_code' => [
                'nullable',
                'string',
                'max:30',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'legal_business_name.required' =>
                'The legal business name is required.',

            'registration_number.unique' =>
                'This business registration number is already registered.',

            'tax_identification_number.unique' =>
                'This tax identification number is already registered.',
        ];
    }
}