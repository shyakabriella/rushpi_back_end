<?php

namespace App\Http\Requests\API\V1\Seller;

use App\Enums\SellerApplicationStatus;
use App\Enums\SellerDocumentType;
use App\Models\SellerApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadSellerDocumentRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        /*
         * The seller application can be provided through route-model binding.
         *
         * Supported route parameter examples:
         * {sellerApplication}
         * {seller_application}
         * {application}
         */
        $application = $this->route('sellerApplication')
            ?? $this->route('seller_application')
            ?? $this->route('application');

        /*
         * When the application is not part of the route, authentication and
         * ownership checks should be completed in the seller controller/service.
         */
        if (! $application instanceof SellerApplication) {
            return true;
        }

        /*
         * Only applications that can still be edited may receive documents.
         */
        if (! in_array($application->status, [
            SellerApplicationStatus::DRAFT,
            SellerApplicationStatus::MORE_INFORMATION_REQUIRED,
        ], true)) {
            return false;
        }

        /*
         * Ensure the authenticated user belongs to the seller profile.
         * Owners and authorized seller members may upload documents.
         */
        return $application->sellerProfile
            ?->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type' => [
                'required',
                Rule::enum(SellerDocumentType::class),
            ],

            'document' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
                'max:10240',
            ],

            'issued_at' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'expires_at' => [
                'nullable',
                'date',
                'after_or_equal:today',
                'after_or_equal:issued_at',
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('document_type')) {
            $this->merge([
                'document_type' => strtolower(
                    trim((string) $this->input('document_type'))
                ),
            ]);
        }
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'document_type.required' =>
                'Please select the type of seller document.',

            'document_type.enum' =>
                'The selected seller document type is invalid.',

            'document.required' =>
                'Please select a document to upload.',

            'document.file' =>
                'The uploaded document must be a valid file.',

            'document.mimes' =>
                'The document must be a PDF, JPG, JPEG, or PNG file.',

            'document.mimetypes' =>
                'The uploaded file content must be a valid PDF, JPEG, or PNG document.',

            'document.max' =>
                'The document must not be larger than 10 MB.',

            'issued_at.date' =>
                'The document issue date must be a valid date.',

            'issued_at.before_or_equal' =>
                'The document issue date cannot be in the future.',

            'expires_at.date' =>
                'The document expiry date must be a valid date.',

            'expires_at.after_or_equal' =>
                'The document expiry date must be today or a future date.',
        ];
    }

    /**
     * Get user-friendly attribute names.
     */
    public function attributes(): array
    {
        return [
            'document_type' => 'document type',
            'document' => 'seller document',
            'issued_at' => 'issue date',
            'expires_at' => 'expiry date',
        ];
    }
}