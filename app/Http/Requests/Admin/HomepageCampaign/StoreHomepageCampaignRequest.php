<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\HomepageCampaign;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHomepageCampaignRequest extends FormRequest
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
        $this->merge([
            'is_active' => $this->has('is_active')
                ? $this->boolean('is_active')
                : true,
            'position' => $this->input('position', 0),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:180',
            ],
            'subtitle' => [
                'nullable',
                'string',
                'max:255',
            ],
            'desktop_image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
            'mobile_image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
            'background_color' => [
                'nullable',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'text_color' => [
                'nullable',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'button_text' => [
                'nullable',
                'string',
                'max:80',
            ],
            'link_type' => [
                'required',
                Rule::in([
                    'category',
                    'brand',
                    'product',
                    'custom',
                    'none',
                ]),
            ],
            'link_value' => [
                Rule::requiredIf(
                    fn (): bool => $this->input('link_type') !== 'none'
                ),
                'nullable',
                'string',
                'max:1000',
            ],
            'card_size' => [
                'required',
                Rule::in([
                    'large',
                    'medium',
                    'small',
                ]),
            ],
            'position' => [
                'required',
                'integer',
                'min:0',
                'max:4294967295',
            ],
            'starts_at' => [
                'nullable',
                'date',
            ],
            'ends_at' => [
                'nullable',
                'date',
                'after:starts_at',
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'desktop_image.required' => 'A desktop campaign image is required.',
            'desktop_image.max' => 'The desktop image may not be larger than 10 MB.',
            'mobile_image.max' => 'The mobile image may not be larger than 10 MB.',
            'background_color.regex' => 'The background color must be a valid hex color.',
            'text_color.regex' => 'The text color must be a valid hex color.',
            'link_value.required' => 'Select where this campaign should link.',
            'ends_at.after' => 'The end date must be after the start date.',
        ];
    }
}
