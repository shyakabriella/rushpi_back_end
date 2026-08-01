<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBrandRequest extends FormRequest
{
    /**
     * Only administrators may create product brands.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->hasAnyRole([
                'admin',
                'superadmin',
            ]);
    }

    /**
     * Validation rules for creating a brand.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('brands', 'name')
                    ->whereNull('deleted_at'),
            ],

            'slug' => [
                'nullable',
                'string',
                'max:180',
                'alpha_dash',
                Rule::unique('brands', 'slug')
                    ->whereNull('deleted_at'),
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'logo_path' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'website_url' => [
                'nullable',
                'url',
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
            'name.required' =>
                'The brand name is required.',

            'name.unique' =>
                'A brand with this name already exists.',

            'name.max' =>
                'The brand name may not exceed 150 characters.',

            'slug.unique' =>
                'A brand with this slug already exists.',

            'slug.alpha_dash' =>
                'The slug may contain only letters, numbers, dashes and underscores.',

            'description.max' =>
                'The brand description may not exceed 5,000 characters.',

            'website_url.url' =>
                'The brand website must be a valid URL.',

            'sort_order.min' =>
                'The brand sort order cannot be negative.',
        ];
    }

    /**
     * Normalize request values before validation.
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

            'description' => is_string(
                $this->input('description')
            )
                ? trim($this->input('description'))
                : $this->input('description'),

            'logo_path' => is_string(
                $this->input('logo_path')
            )
                ? trim($this->input('logo_path'))
                : $this->input('logo_path'),

            'website_url' => is_string(
                $this->input('website_url')
            )
                ? trim($this->input('website_url'))
                : $this->input('website_url'),

            'is_active' => $this->has('is_active')
                ? $this->boolean('is_active')
                : true,

            'sort_order' => $this->input(
                'sort_order',
                0
            ),
        ]);
    }
}
