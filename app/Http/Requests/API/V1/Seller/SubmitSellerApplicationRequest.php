<?php

declare(strict_types=1);

namespace App\Http\Requests\API\V1\Seller;

use App\Models\SellerApplication;
use App\Models\SellerProfile;
use Illuminate\Foundation\Http\FormRequest;

class SubmitSellerApplicationRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user can submit
     * this seller-verification application.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        $sellerProfile = $this->route('sellerProfile');
        $sellerApplication = $this->route('sellerApplication');

        if ($user === null) {
            return false;
        }

        if (! $sellerProfile instanceof SellerProfile) {
            return false;
        }

        if (! $sellerApplication instanceof SellerApplication) {
            return false;
        }

        /*
         * The application must belong to the seller profile
         * appearing in the URL.
         */
        if (
            $sellerApplication->seller_profile_id
            !== $sellerProfile->id
        ) {
            return false;
        }

        /*
         * Only an active seller owner may submit the application.
         */
        return $user->ownsSeller($sellerProfile);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'seller_message' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'confirm_information_is_correct' => [
                'required',
                'accepted',
            ],

            'confirm_documents_are_authentic' => [
                'required',
                'accepted',
            ],

            'agree_to_verification' => [
                'required',
                'accepted',
            ],
        ];
    }

    /**
     * Return customized validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'seller_message.max' =>
                'The seller message may not exceed 2,000 characters.',

            'confirm_information_is_correct.required' =>
                'You must confirm that the submitted business information is correct.',

            'confirm_information_is_correct.accepted' =>
                'You must confirm that the submitted business information is correct.',

            'confirm_documents_are_authentic.required' =>
                'You must confirm that the uploaded documents are authentic.',

            'confirm_documents_are_authentic.accepted' =>
                'You must confirm that the uploaded documents are authentic.',

            'agree_to_verification.required' =>
                'You must agree to the seller-verification process.',

            'agree_to_verification.accepted' =>
                'You must agree to the seller-verification process.',
        ];
    }

    /**
     * Return only the values that should be stored.
     *
     * The confirmation fields are validation controls and should
     * not be written directly into seller_applications.
     *
     * @return array<string, mixed>
     */
    public function applicationData(): array
    {
        return [
            'seller_message' => $this->validated(
                'seller_message'
            ),
        ];
    }
}
