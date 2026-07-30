<?php

declare(strict_types=1);

namespace App\Http\Requests\API\V1\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSellerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $sellerProfile = $this->route('sellerProfile');

        return [
            'legal_business_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'trading_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'registration_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique(
                    'seller_profiles',
                    'registration_number'
                )->ignore($sellerProfile?->id),
            ],

            'tax_identification_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique(
                    'seller_profiles',
                    'tax_identification_number'
                )->ignore($sellerProfile?->id),
            ],

            'business_email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
            ],

            'business_phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],

            'website' => [
                'sometimes',
                'nullable',
                'url',
                'max:255',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}