<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDepartmentRequest
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
        $normalized = [];

        foreach (
            [
                'name',
                'slug',
                'description',
                'image_path',
            ]
            as $field
        ) {
            if (
                $this->exists($field)
                && is_string(
                    $this->input($field)
                )
            ) {
                $normalized[$field] =
                    trim(
                        (string)
                        $this->input(
                            $field
                        )
                    );
            }
        }

        if (
            $this->exists(
                'is_active'
            )
        ) {
            $normalized['is_active'] =
                $this->boolean(
                    'is_active'
                );
        }

        if ($normalized !== []) {
            $this->merge(
                $normalized
            );
        }
    }

    public function rules(): array
    {
        $department =
            $this->department();

        $id =
            $department?->getKey();

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',

                Rule::unique(
                    'departments',
                    'name'
                )
                    ->ignore($id)
                    ->whereNull(
                        'deleted_at'
                    ),
            ],

            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:180',
                'alpha_dash',

                Rule::unique(
                    'departments',
                    'slug'
                )
                    ->ignore($id)
                    ->whereNull(
                        'deleted_at'
                    ),
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'image_path' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
                'max:4294967295',
            ],
        ];
    }

    private function department(): ?Department
    {
        $department =
            $this->route(
                'department'
            );

        if (
            $department
            instanceof Department
        ) {
            return $department;
        }

        if (
            is_string($department)
            && $department !== ''
        ) {
            return Department::query()
                ->where(
                    'public_id',
                    $department
                )
                ->first();
        }

        return null;
    }
}