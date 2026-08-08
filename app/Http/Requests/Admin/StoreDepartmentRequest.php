<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDepartmentRequest
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
        $this->merge([
            'name' =>
                is_string(
                    $this->input('name')
                )
                    ? trim(
                        $this->input(
                            'name'
                        )
                    )
                    : $this->input(
                        'name'
                    ),

            'slug' =>
                is_string(
                    $this->input('slug')
                )
                    ? trim(
                        $this->input(
                            'slug'
                        )
                    )
                    : $this->input(
                        'slug'
                    ),

            'description' =>
                is_string(
                    $this->input(
                        'description'
                    )
                )
                    ? trim(
                        $this->input(
                            'description'
                        )
                    )
                    : $this->input(
                        'description'
                    ),

            'image_path' =>
                is_string(
                    $this->input(
                        'image_path'
                    )
                )
                    ? trim(
                        $this->input(
                            'image_path'
                        )
                    )
                    : $this->input(
                        'image_path'
                    ),

            'is_active' =>
                $this->has(
                    'is_active'
                )
                    ? $this->boolean(
                        'is_active'
                    )
                    : true,

            'sort_order' =>
                $this->input(
                    'sort_order',
                    0
                ),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',

                Rule::unique(
                    'departments',
                    'name'
                )->whereNull(
                    'deleted_at'
                ),
            ],

            'slug' => [
                'nullable',
                'string',
                'max:180',
                'alpha_dash',

                Rule::unique(
                    'departments',
                    'slug'
                )->whereNull(
                    'deleted_at'
                ),
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'image_path' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'is_active' => [
                'boolean',
            ],

            'sort_order' => [
                'integer',
                'min:0',
                'max:4294967295',
            ],
        ];
    }
}