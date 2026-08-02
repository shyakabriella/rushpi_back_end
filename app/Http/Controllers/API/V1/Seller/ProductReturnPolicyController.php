<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Seller;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\UpsertProductReturnPolicyRequest;
use App\Http\Resources\ProductReturnPolicyResource;
use App\Models\Product;
use App\Models\ProductReturnPolicy;
use App\Models\SellerProfile;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProductReturnPolicyController extends Controller
{
    /**
     * Display the return policy configured for a seller product.
     */
    public function show(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        $this->ensureProductBelongsToSeller(
            $sellerProfile,
            $product
        );

        $policy = $product
            ->returnPolicy()
            ->with([
                'product',
                'createdBy',
                'updatedBy',
            ])
            ->first();

        if (!$policy instanceof ProductReturnPolicy) {
            return response()->json([
                'success' => true,

                'message' =>
                    'No return policy has been configured for this product.',

                'data' => null,

                'options' =>
                    $this->configurationOptions(),
            ]);
        }

        return (
            new ProductReturnPolicyResource(
                $policy
            )
        )
            ->additional([
                'success' => true,

                'message' =>
                    'Product return policy retrieved successfully.',

                'options' =>
                    $this->configurationOptions(),
            ])
            ->response();
    }

    /**
     * Create or replace a product return policy.
     */
    public function upsert(
        UpsertProductReturnPolicyRequest $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        $this->ensureProductBelongsToSeller(
            $sellerProfile,
            $product
        );

        $status = $this->productStatus(
            $product
        );

        if (
            in_array(
                $status,
                [
                    ProductStatus::PENDING_REVIEW,
                    ProductStatus::SUSPENDED,
                    ProductStatus::ARCHIVED,
                ],
                true
            )
        ) {
            return $this->statusConflictResponse(
                $status,
                'The return policy cannot be changed while the product has this status.'
            );
        }

        $userId = $request->user()
            ?->getKey();

        [
            'policy' => $policy,
            'created' => $created,
            'returned_to_draft' =>
                $returnedToDraft,
        ] = DB::transaction(
            function () use (
                $request,
                $sellerProfile,
                $product,
                $userId
            ): array {
                $lockedProduct = Product::query()
                    ->whereKey(
                        $product->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureProductBelongsToSeller(
                    $sellerProfile,
                    $lockedProduct
                );

                $currentStatus =
                    $this->productStatus(
                        $lockedProduct
                    );

                if (
                    in_array(
                        $currentStatus,
                        [
                            ProductStatus::PENDING_REVIEW,
                            ProductStatus::SUSPENDED,
                            ProductStatus::ARCHIVED,
                        ],
                        true
                    )
                ) {
                    abort(
                        409,
                        'The return policy cannot be changed while the product has this status.'
                    );
                }

                $policy = ProductReturnPolicy::query()
                    ->where(
                        'product_id',
                        $lockedProduct->getKey()
                    )
                    ->lockForUpdate()
                    ->first();

                $created =
                    !$policy instanceof
                    ProductReturnPolicy;

                if ($created) {
                    $policy =
                        new ProductReturnPolicy();

                    $policy->product_id =
                        $lockedProduct->getKey();

                    if ($userId !== null) {
                        $policy->created_by =
                            $userId;
                    }
                }

                $policy->fill(
                    $request->policyData()
                );

                if ($userId !== null) {
                    $policy->updated_by =
                        $userId;
                }

                $policyChanged =
                    $created
                    || $policy->isDirty();

                $policy->save();

                $returnedToDraft = false;

                /*
                 * Approved and rejected products must return to draft after
                 * changing customer-facing return conditions.
                 */

                if (
                    $policyChanged
                    && in_array(
                        $currentStatus,
                        [
                            ProductStatus::APPROVED,
                            ProductStatus::REJECTED,
                        ],
                        true
                    )
                ) {
                    $this->returnProductToDraft(
                        $lockedProduct
                    );

                    $lockedProduct->save();

                    $returnedToDraft = true;
                }

                return [
                    'policy' => $policy,
                    'created' => $created,
                    'returned_to_draft' =>
                        $returnedToDraft,
                ];
            }
        );

        $policy->load([
            'product',
            'createdBy',
            'updatedBy',
        ]);

        $message = match (true) {
            $created && $returnedToDraft =>
                'Return policy created successfully and the product was returned to draft for moderation.',

            $created =>
                'Product return policy created successfully.',

            $returnedToDraft =>
                'Return policy updated successfully and the product was returned to draft for moderation.',

            default =>
                'Product return policy updated successfully.',
        };

        return (
            new ProductReturnPolicyResource(
                $policy
            )
        )
            ->additional([
                'success' => true,

                'message' => $message,

                'options' =>
                    $this->configurationOptions(),
            ])
            ->response()
            ->setStatusCode(
                $created
                    ? 201
                    : 200
            );
    }

    /**
     * Delete a product return policy.
     */
    public function destroy(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        $this->ensureProductBelongsToSeller(
            $sellerProfile,
            $product
        );

        $status = $this->productStatus(
            $product
        );

        if (
            in_array(
                $status,
                [
                    ProductStatus::PENDING_REVIEW,
                    ProductStatus::SUSPENDED,
                    ProductStatus::ARCHIVED,
                ],
                true
            )
        ) {
            return $this->statusConflictResponse(
                $status,
                'The return policy cannot be deleted while the product has this status.'
            );
        }

        $returnedToDraft = DB::transaction(
            function () use (
                $sellerProfile,
                $product
            ): bool {
                $lockedProduct = Product::query()
                    ->whereKey(
                        $product->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureProductBelongsToSeller(
                    $sellerProfile,
                    $lockedProduct
                );

                $currentStatus =
                    $this->productStatus(
                        $lockedProduct
                    );

                if (
                    in_array(
                        $currentStatus,
                        [
                            ProductStatus::PENDING_REVIEW,
                            ProductStatus::SUSPENDED,
                            ProductStatus::ARCHIVED,
                        ],
                        true
                    )
                ) {
                    abort(
                        409,
                        'The return policy cannot be deleted while the product has this status.'
                    );
                }

                $policy = ProductReturnPolicy::query()
                    ->where(
                        'product_id',
                        $lockedProduct->getKey()
                    )
                    ->lockForUpdate()
                    ->first();

                if (
                    !$policy instanceof
                    ProductReturnPolicy
                ) {
                    return false;
                }

                $policy->delete();

                if (
                    in_array(
                        $currentStatus,
                        [
                            ProductStatus::APPROVED,
                            ProductStatus::REJECTED,
                        ],
                        true
                    )
                ) {
                    $this->returnProductToDraft(
                        $lockedProduct
                    );

                    $lockedProduct->save();

                    return true;
                }

                return false;
            }
        );

        return response()->json([
            'success' => true,

            'message' => $returnedToDraft
                ? 'Return policy deleted successfully and the product was returned to draft.'
                : 'Product return policy deleted successfully.',

            'data' => null,
        ]);
    }

    /**
     * Ensure the nested product belongs to the selected seller profile.
     */
    private function ensureProductBelongsToSeller(
        SellerProfile $sellerProfile,
        Product $product
    ): void {
        abort_unless(
            (int) $product->seller_profile_id
                === (int) $sellerProfile->getKey(),
            404,
            'The selected product does not belong to this seller profile.'
        );
    }

    /**
     * Read product status regardless of whether enum casting is enabled.
     */
    private function productStatus(
        Product $product
    ): ProductStatus {
        $status = $product->status;

        if ($status instanceof ProductStatus) {
            return $status;
        }

        if ($status instanceof BackedEnum) {
            $status = $status->value;
        }

        return ProductStatus::from(
            (string) $status
        );
    }

    /**
     * Return a product to draft after changing moderated information.
     */
    private function returnProductToDraft(
        Product $product
    ): void {
        $this->setExistingAttributes(
            $product,
            [
                'status' =>
                    ProductStatus::DRAFT->value,

                'submitted_at' =>
                    null,

                'approved_at' =>
                    null,

                'approved_by' =>
                    null,

                'rejected_at' =>
                    null,

                'rejected_by' =>
                    null,

                'rejection_reason' =>
                    null,

                'suspended_at' =>
                    null,

                'suspended_by' =>
                    null,

                'suspension_reason' =>
                    null,

                'archived_at' =>
                    null,
            ]
        );
    }

    /**
     * Set values only when their columns exist on the current model.
     *
     * @param array<string, mixed> $values
     */
    private function setExistingAttributes(
        Product $product,
        array $values
    ): void {
        $attributes =
            $product->getAttributes();

        foreach ($values as $key => $value) {
            if (
                $key === 'status'
                || array_key_exists(
                    $key,
                    $attributes
                )
            ) {
                $product->setAttribute(
                    $key,
                    $value
                );
            }
        }
    }

    /**
     * Return a standardized lifecycle conflict response.
     */
    private function statusConflictResponse(
        ProductStatus $status,
        string $message
    ): JsonResponse {
        return response()->json([
            'success' => false,

            'message' => $message,

            'errors' => [
                'status' => [
                    sprintf(
                        'The current product status is %s.',
                        $status->value
                    ),
                ],
            ],
        ], 409);
    }

    /**
     * Return form options used by seller web and mobile applications.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    private function configurationOptions(): array
    {
        return [
            'shipping_payers' =>
                collect(
                    ProductReturnPolicy
                        ::shippingPayers()
                )
                    ->map(
                        static fn (
                            string $value
                        ): array => [
                            'value' => $value,

                            'label' =>
                                Str::headline(
                                    $value
                                ),
                        ]
                    )
                    ->values()
                    ->all(),

            'accepted_conditions' =>
                collect(
                    ProductReturnPolicy
                        ::commonConditions()
                )
                    ->map(
                        static fn (
                            string $value
                        ): array => [
                            'value' => $value,

                            'label' =>
                                Str::headline(
                                    $value
                                ),
                        ]
                    )
                    ->values()
                    ->all(),

            'refund_methods' =>
                collect(
                    ProductReturnPolicy
                        ::supportedRefundMethods()
                )
                    ->map(
                        static fn (
                            string $value
                        ): array => [
                            'value' => $value,

                            'label' =>
                                Str::headline(
                                    $value
                                ),
                        ]
                    )
                    ->values()
                    ->all(),
        ];
    }
}
