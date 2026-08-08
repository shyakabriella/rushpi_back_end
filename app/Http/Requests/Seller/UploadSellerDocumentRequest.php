<?php

declare(strict_types=1);

namespace App\Http\Requests\API\V1\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadSellerDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             * The verification requirement table is the source of truth.
             * Do not validate document_type against SellerDocumentType enum.
             */
            'document_type' => [
                'required',
                'string',
                'max:100',
                Rule::exists(
                    'seller_document_requirements',
                    'key'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'is_active',
                            true
                        )
                ),
            ],

            'document' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
            ],

            'issued_at' => [
                'sometimes',
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'expires_at' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:today',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_type.exists' =>
                'The selected verification document type is not active or does not exist.',

            'document.required' =>
                'Select a verification document to upload.',

            'document.mimes' =>
                'Only PDF, JPEG and PNG documents are supported.',

            'document.max' =>
                'The verification document may not be larger than 10 MB.',
        ];
    }
}