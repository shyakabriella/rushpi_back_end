<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Only administrators and super administrators
     * may create product categories.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole([
            'admin',
            'superadmin',
        ]);
    }

    /**
     * Validation rules for creating a category.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')
                    ->whereNull('deleted_at'),
            ],

            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('categories', 'name')
                    ->whereNull('deleted_at'),
            ],

            'slug' => [
                'nullable',
                'string',
                'max:180',
                'alpha_dash',
                Rule::unique('categories', 'slug')
                    ->whereNull('deleted_at'),
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

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.exists' => 'The selected parent category does not exist.',

            'name.required' => 'The category name is required.',
            'name.unique' => 'A category with this name already exists.',
            'name.max' => 'The category name may not exceed 150 characters.',

            'slug.unique' => 'A category with this slug already exists.',
            'slug.alpha_dash' => 'The slug may contain only letters, numbers, dashes and underscores.',

            'description.max' => 'The category description may not exceed 5,000 characters.',

            'sort_order.min' => 'The category sort order cannot be negative.',
        ];
    }

    /**
     * Prepare input before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name'))
                ? trim($this->input('name'))
                : $this->input('name'),

            'slug' => is_string($this->input('slug'))
                ? trim($this->input('slug'))
                : $this->input('slug'),

            'description' => is_string($this->input('description'))
                ? trim($this->input('description'))
                : $this->input('description'),

            'is_active' => $this->has('is_active')
                ? $this->boolean('is_active')
                : true,

            'sort_order' => $this->input('sort_order', 0),
        ]);
    }
}