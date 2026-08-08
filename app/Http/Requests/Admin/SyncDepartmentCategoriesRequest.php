<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SyncDepartmentCategoriesRequest
    extends FormRequest
{
    public function authorize(): bool
    {
        return $this
            ->user()
            ?->hasAnyRole([
                'admin',
                'superadmin',
            ])
            ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (
            $this->exists(
                'move_existing'
            )
        ) {
            $this->merge([
                'move_existing' =>
                    $this->boolean(
                        'move_existing'
                    ),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'categories' => [
                'required',
                'array',
                'max:500',
            ],

            'categories.*.category_public_id' => [
                'required',
                'string',
                'size:26',
                'distinct',

                Rule::exists(
                    'categories',
                    'public_id'
                )->whereNull(
                    'deleted_at'
                ),
            ],

            'categories.*.sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:4294967295',
            ],

            'categories.*.is_featured' => [
                'nullable',
                'boolean',
            ],

            'categories.*.is_active' => [
                'nullable',
                'boolean',
            ],

            'move_existing' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}