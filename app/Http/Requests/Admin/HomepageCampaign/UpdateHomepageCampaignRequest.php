<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\HomepageCampaign;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHomepageCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([
            'admin',
            'superadmin',
        ]) === true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        if ($this->has('is_active')) {
            $values['is_active'] =
                $this->boolean('is_active');
        }

        if ($this->has('remove_mobile_image')) {
            $values['remove_mobile_image'] =
                $this->boolean('remove_mobile_image');
        }

        if ($values !== []) {
            $this->merge($values);
        }
    }

    public function rules(): array
    {
        return [
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:180',
            ],
            'subtitle' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'desktop_image' => [
                'sometimes',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
            'mobile_image' => [
                'sometimes',
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
            'remove_mobile_image' => [
                'sometimes',
                'boolean',
            ],
            'background_color' => [
                'sometimes',
                'required',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'text_color' => [
                'sometimes',
                'required',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'button_text' => [
                'sometimes',
                'nullable',
                'string',
                'max:80',
            ],
            'link_type' => [
                'sometimes',
                Rule::in([
                    'category',
                    'brand',
                    'product',
                    'custom',
                    'none',
                ]),
            ],
            'link_value' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
            'card_size' => [
                'sometimes',
                Rule::in([
                    'large',
                    'medium',
                    'small',
                ]),
            ],
            'position' => [
                'sometimes',
                'integer',
                'min:0',
                'max:4294967295',
            ],
            'starts_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'ends_at' => [
                'sometimes',
                'nullable',
                'date',
                'after:starts_at',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'desktop_image.max' => 'The desktop image may not be larger than 10 MB.',
            'mobile_image.max' => 'The mobile image may not be larger than 10 MB.',
            'background_color.regex' => 'The background color must be a valid hex color.',
            'text_color.regex' => 'The text color must be a valid hex color.',
            'ends_at.after' => 'The end date must be after the start date.',
        ];
    }
}
