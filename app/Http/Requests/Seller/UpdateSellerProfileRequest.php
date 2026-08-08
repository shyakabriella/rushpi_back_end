<?php

declare(strict_types=1);

namespace App\Http\Requests\API\V1\Seller;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSellerProfileRequest extends FormRequest
{
    /**
     * Ownership / seller authorization is enforced
     * by SellerProfileController::update().
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Store identity
            |--------------------------------------------------------------------------
            */

            'business_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'legal_business_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'store_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'trading_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'business_type' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            /*
            |--------------------------------------------------------------------------
            | Contact
            |--------------------------------------------------------------------------
            */

            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],

            'business_phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],

            'whatsapp' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],

            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
            ],

            'business_email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
            ],

            /*
            |--------------------------------------------------------------------------
            | Registration
            |--------------------------------------------------------------------------
            */

            'registration_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'tin_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'tax_identification_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            /*
            |--------------------------------------------------------------------------
            | Policies
            |--------------------------------------------------------------------------
            */

            'return_policy' => [
                'sometimes',
                'nullable',
                'string',
                'max:10000',
            ],

            'warranty_policy' => [
                'sometimes',
                'nullable',
                'string',
                'max:10000',
            ],

            /*
            |--------------------------------------------------------------------------
            | Business address
            |--------------------------------------------------------------------------
            |
            | The frontend sends:
            |
            | address[country]
            | address[province]
            | address[district]
            | address[sector]
            | address[address_line]
            |
            */

            'address' => [
                'sometimes',
                'array',
            ],

            'address.country' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'address.province' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'address.district' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'address.sector' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'address.cell' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'address.village' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'address.address_line' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],

            'address.postal_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            /*
            |--------------------------------------------------------------------------
            | Branding
            |--------------------------------------------------------------------------
            */

            'logo' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'cover_image' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ];
    }
}