<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ProductModerationAction;
use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ModerateProductRequest extends FormRequest
{
    /**
     * Only administrators and super administrators may
     * perform product moderation actions.
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
     * Validation rules for product moderation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             * Supported moderation actions:
             *
             * - approve
             * - reject
             * - suspend
             *
             * The exact stored values are validated using
             * ProductModerationAction.
             */
            'action' => [
                'required',
                Rule::enum(ProductModerationAction::class),
            ],

            /*
             * A reason is optional for approval but required
             * for rejection and suspension.
             */
            'reason' => [
                'nullable',
                'string',
                'max:2000',
            ],

            /*
             * Internal notes are visible only to administrators.
             * They must never appear in the public catalog.
             */
            'internal_notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    /**
     * Validate moderation business rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(
            function (Validator $validator): void {
                if (
                    $validator->errors()->has('action')
                ) {
                    return;
                }

                $product = $this->product();

                if ($product === null) {
                    $validator->errors()->add(
                        'product',
                        'The product selected for moderation was not found.'
                    );

                    return;
                }

                $action = $this->actionKind();

                if ($action === null) {
                    $validator->errors()->add(
                        'action',
                        'The selected product moderation action is not supported.'
                    );

                    return;
                }

                $status = $this->productStatus($product);

                if ($status === null) {
                    $validator->errors()->add(
                        'product',
                        'The product has an invalid moderation status.'
                    );

                    return;
                }

                $this->validateStatusTransition(
                    validator: $validator,
                    action: $action,
                    status: $status
                );

                $this->validateReason(
                    validator: $validator,
                    action: $action
                );
            }
        );
    }

    /**
     * Validate whether the requested moderation action may
     * be performed from the product's current status.
     */
    private function validateStatusTransition(
        Validator $validator,
        string $action,
        ProductStatus $status
    ): void {
        if (
            in_array(
                $action,
                ['approve', 'reject'],
                true
            )
            && $status !== ProductStatus::PENDING_REVIEW
        ) {
            $validator->errors()->add(
                'action',
                sprintf(
                    'Only products with pending review status may be %s.',
                    $action === 'approve'
                        ? 'approved'
                        : 'rejected'
                )
            );

            return;
        }

        if (
            $action === 'suspend'
            && $status !== ProductStatus::APPROVED
        ) {
            $validator->errors()->add(
                'action',
                'Only an approved product may be suspended.'
            );
        }
    }

    /**
     * Require a meaningful seller-facing reason for
     * rejection and suspension.
     */
    private function validateReason(
        Validator $validator,
        string $action
    ): void {
        if (
            ! in_array(
                $action,
                ['reject', 'suspend'],
                true
            )
        ) {
            return;
        }

        $reason = trim(
            (string) $this->input('reason', '')
        );

        if ($reason === '') {
            $validator->errors()->add(
                'reason',
                sprintf(
                    'A reason is required when a product is %s.',
                    $action === 'reject'
                        ? 'rejected'
                        : 'suspended'
                )
            );

            return;
        }

        if (mb_strlen($reason) < 10) {
            $validator->errors()->add(
                'reason',
                'The moderation reason must contain at least 10 characters.'
            );
        }
    }

    /**
     * Normalize moderation input before validation.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('action')) {
            $action = $this->input('action');

            $normalized['action'] = is_string($action)
                ? strtolower(trim($action))
                : $action;
        }

        foreach ([
            'reason',
            'internal_notes',
        ] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '') {
                $value = null;
            }

            $normalized[$field] = $value;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' =>
                'A product moderation action is required.',

            'action.enum' =>
                'The selected product moderation action is invalid.',

            'reason.string' =>
                'The moderation reason must be text.',

            'reason.max' =>
                'The moderation reason may not exceed 2,000 characters.',

            'internal_notes.string' =>
                'The internal moderation notes must be text.',

            'internal_notes.max' =>
                'The internal moderation notes may not exceed 5,000 characters.',
        ];
    }

    /**
     * Convert the selected enum action into one of the
     * controller-supported moderation operations.
     */
    private function actionKind(): ?string
    {
        $submittedAction = strtolower(
            trim((string) $this->input('action'))
        );

        foreach (
            ProductModerationAction::cases()
            as $moderationAction
        ) {
            if (
                strtolower((string) $moderationAction->value)
                !== $submittedAction
            ) {
                continue;
            }

            $identity = strtolower(
                $moderationAction->name
                .' '
                .$moderationAction->value
            );

            if (str_contains($identity, 'approv')) {
                return 'approve';
            }

            if (str_contains($identity, 'reject')) {
                return 'reject';
            }

            if (str_contains($identity, 'suspend')) {
                return 'suspend';
            }
        }

        return null;
    }

    /**
     * Resolve the product status enum safely.
     */
    private function productStatus(
        Product $product
    ): ?ProductStatus {
        if ($product->status instanceof ProductStatus) {
            return $product->status;
        }

        if (is_string($product->status)) {
            return ProductStatus::tryFrom(
                $product->status
            );
        }

        return null;
    }

    /**
     * Resolve the product from route model binding.
     */
    private function product(): ?Product
    {
        $product = $this->route('product');

        if ($product instanceof Product) {
            return $product;
        }

        if (
            is_string($product)
            && $product !== ''
        ) {
            return Product::query()
                ->where('public_id', $product)
                ->first();
        }

        return null;
    }
}
