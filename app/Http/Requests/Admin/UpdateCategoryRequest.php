<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Only administrators and super administrators
     * may update product categories.
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
     * Validation rules for updating a category.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->category();
        $categoryId = $category?->getKey();

        return [
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',

                Rule::exists('categories', 'id')
                    ->whereNull('deleted_at'),

                Rule::notIn(
                    $categoryId !== null
                        ? [$categoryId]
                        : []
                ),
            ],

            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',

                Rule::unique('categories', 'name')
                    ->ignore($categoryId)
                    ->whereNull('deleted_at'),
            ],

            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:180',
                'alpha_dash',

                Rule::unique('categories', 'slug')
                    ->ignore($categoryId)
                    ->whereNull('deleted_at'),
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

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.exists' =>
                'The selected parent category does not exist.',

            'parent_id.not_in' =>
                'A category cannot be its own parent.',

            'name.required' =>
                'The category name cannot be empty.',

            'name.unique' =>
                'Another category already uses this name.',

            'name.max' =>
                'The category name may not exceed 150 characters.',

            'slug.unique' =>
                'Another category already uses this slug.',

            'slug.alpha_dash' =>
                'The slug may contain only letters, numbers, dashes and underscores.',

            'description.max' =>
                'The category description may not exceed 5,000 characters.',

            'sort_order.min' =>
                'The category sort order cannot be negative.',
        ];
    }

    /**
     * Normalize only fields included in the request.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('name')) {
            $normalized['name'] = is_string($this->input('name'))
                ? trim($this->input('name'))
                : $this->input('name');
        }

        if ($this->has('slug')) {
            $normalized['slug'] = is_string($this->input('slug'))
                ? trim($this->input('slug'))
                : $this->input('slug');
        }

        if ($this->has('description')) {
            $normalized['description'] =
                is_string($this->input('description'))
                    ? trim($this->input('description'))
                    : $this->input('description');
        }

        if ($this->has('image_path')) {
            $normalized['image_path'] =
                is_string($this->input('image_path'))
                    ? trim($this->input('image_path'))
                    : $this->input('image_path');
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
     * Resolve the category from route model binding.
     */
    private function category(): ?Category
    {
        $category = $this->route('category');

        if ($category instanceof Category) {
            return $category;
        }

        if (is_string($category) && $category !== '') {
            return Category::query()
                ->where('public_id', $category)
                ->first();
        }

        return null;
    }
}
