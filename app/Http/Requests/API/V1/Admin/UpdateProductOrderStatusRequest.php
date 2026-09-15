<?php

declare(strict_types=1);

namespace App\Http\Requests\API\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProductOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    'confirmed',
                    'processing',
                    'shipped',
                    'delivered',
                    'cancelled',
                ]),
            ],
            'reason' => [
                'required_if:status,cancelled',
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
