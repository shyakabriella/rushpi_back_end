<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    /**
     * Only administrators may update product brands.
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
     * Validation rules for updating a brand.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $brand = $this->brand();
        $brandId = $brand?->getKey();

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',

                Rule::unique('brands', 'name')
                    ->ignore($brandId)
                    ->whereNull('deleted_at'),
            ],

            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:180',
                'alpha_dash',

                Rule::unique('brands', 'slug')
                    ->ignore($brandId)
                    ->whereNull('deleted_at'),
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'logo_path' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],

            'website_url' => [
                'sometimes',
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
                'The brand name cannot be empty.',

            'name.unique' =>
                'Another brand already uses this name.',

            'name.max' =>
                'The brand name may not exceed 150 characters.',

            'slug.unique' =>
                'Another brand already uses this slug.',

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
     * Normalize only values included in the request.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('name')) {
            $normalized['name'] = is_string(
                $this->input('name')
            )
                ? trim($this->input('name'))
                : $this->input('name');
        }

        if ($this->has('slug')) {
            $normalized['slug'] = is_string(
                $this->input('slug')
            )
                ? trim($this->input('slug'))
                : $this->input('slug');
        }

        if ($this->has('description')) {
            $normalized['description'] = is_string(
                $this->input('description')
            )
                ? trim($this->input('description'))
                : $this->input('description');
        }

        if ($this->has('logo_path')) {
            $normalized['logo_path'] = is_string(
                $this->input('logo_path')
            )
                ? trim($this->input('logo_path'))
                : $this->input('logo_path');
        }

        if ($this->has('website_url')) {
            $normalized['website_url'] = is_string(
                $this->input('website_url')
            )
                ? trim($this->input('website_url'))
                : $this->input('website_url');
        }

        if ($this->has('is_active')) {
            $normalized['is_active'] =
                $this->boolean('is_active');
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * Resolve the brand from route model binding.
     */
    private function brand(): ?Brand
    {
        $brand = $this->route('brand');

        if ($brand instanceof Brand) {
            return $brand;
        }

        if (is_string($brand) && $brand !== '') {
            return Brand::query()
                ->where('public_id', $brand)
                ->first();
        }

        return null;
    }
}
