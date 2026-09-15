<?php

declare(strict_types=1);

namespace App\Http\Requests\API\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProductOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(
                trim((string) $this->input('email'))
            ),
            'payment_method' => strtolower(
                trim((string) $this->input(
                    'payment_method'
                ))
            ),
            'delivery_method' => strtolower(
                trim((string) $this->input(
                    'delivery_method'
                ))
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => [
                'required',
                'string',
                'max:100',
            ],
            'last_name' => [
                'required',
                'string',
                'max:100',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
            ],
            'phone' => [
                'required',
                'string',
                'max:40',
            ],
            'payment_method' => [
                'required',
                Rule::in([
                    'mtn_momo',
                    'airtel_money',
                    'card',
                    'cash',
                ]),
            ],
            'delivery_method' => [
                'required',
                Rule::in([
                    'standard',
                    'express',
                    'pickup',
                ]),
            ],
            'delivery_province' => [
                'required_unless:delivery_method,pickup',
                'nullable',
                'string',
                'max:150',
            ],
            'delivery_district' => [
                'required_unless:delivery_method,pickup',
                'nullable',
                'string',
                'max:150',
            ],
            'delivery_sector' => [
                'required_unless:delivery_method,pickup',
                'nullable',
                'string',
                'max:150',
            ],
            'delivery_street' => [
                'required_unless:delivery_method,pickup',
                'nullable',
                'string',
                'max:255',
            ],
            'delivery_instructions' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
                'max:50',
            ],
            'items.*.product_public_id' => [
                'required',
                'string',
                'exists:products,public_id',
            ],
            'items.*.variant_public_id' => [
                'nullable',
                'string',
                'exists:product_variants,public_id',
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
