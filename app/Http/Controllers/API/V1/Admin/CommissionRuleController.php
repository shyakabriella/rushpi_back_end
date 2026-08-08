<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCommissionRuleRequest;
use App\Http\Requests\Admin\UpdateCommissionRuleRequest;
use App\Http\Resources\Admin\CommissionRuleResource;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class CommissionRuleController extends Controller
{
    /**
     * Display a paginated list of commission rules.
     */
    public function index(
        Request $request
    ): JsonResponse {
        $validated =
            $request->validate([
                'q' => [
                    'nullable',
                    'string',
                    'max:150',
                ],

                'scope' => [
                    'nullable',

                    Rule::in([
                        CommissionRule::SCOPE_GLOBAL,
                        CommissionRule::SCOPE_DEPARTMENT,
                        CommissionRule::SCOPE_CATEGORY,
                    ]),
                ],

                'commission_type' => [
                    'nullable',

                    Rule::in([
                        CommissionRule::TYPE_PERCENTAGE,
                        CommissionRule::TYPE_FIXED,
                    ]),
                ],

                'department_public_id' => [
                    'nullable',
                    'string',
                    'size:26',
                ],

                'category_public_id' => [
                    'nullable',
                    'string',
                    'size:26',
                ],

                'is_active' => [
                    'nullable',
                    'boolean',
                ],

                'currently_effective' => [
                    'nullable',
                    'boolean',
                ],

                'sort_by' => [
                    'nullable',

                    Rule::in([
                        'name',
                        'scope',
                        'commission_value',
                        'priority',
                        'starts_at',
                        'created_at',
                        'updated_at',
                    ]),
                ],

                'sort_direction' => [
                    'nullable',

                    Rule::in([
                        'asc',
                        'desc',
                    ]),
                ],

                'per_page' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:100',
                ],
            ]);

        $query =
            CommissionRule::query()
                ->with([
                    'department:id,public_id,name,slug',
                    'category:id,public_id,name,slug',

                    'createdBy:id,name,email',
                    'updatedBy:id,name,email',
                ]);

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['q']
            )
            && trim(
                (string)
                $validated['q']
            ) !== ''
        ) {
            $query->search(
                (string)
                $validated['q']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Scope
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated['scope']
            )
        ) {
            $query->where(
                'scope',
                $validated['scope']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Commission type
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated[
                    'commission_type'
                ]
            )
        ) {
            $query->where(
                'commission_type',
                $validated[
                    'commission_type'
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Active status
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists(
                'is_active',
                $validated
            )
        ) {
            $query->where(
                'is_active',
                $request->boolean(
                    'is_active'
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Effective now
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists(
                'currently_effective',
                $validated
            )
            && $request->boolean(
                'currently_effective'
            )
        ) {
            $query
                ->active()
                ->currentlyEffective();
        }

        /*
        |--------------------------------------------------------------------------
        | Department filter
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated[
                    'department_public_id'
                ]
            )
        ) {
            $departmentPublicId =
                (string)
                $validated[
                    'department_public_id'
                ];

            $query->whereHas(
                'department',
                static function (
                    $departmentQuery
                ) use (
                    $departmentPublicId
                ): void {
                    $departmentQuery->where(
                        'public_id',
                        $departmentPublicId
                    );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Category filter
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $validated[
                    'category_public_id'
                ]
            )
        ) {
            $categoryPublicId =
                (string)
                $validated[
                    'category_public_id'
                ];

            $query->whereHas(
                'category',
                static function (
                    $categoryQuery
                ) use (
                    $categoryPublicId
                ): void {
                    $categoryQuery->where(
                        'public_id',
                        $categoryPublicId
                    );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Sorting
        |--------------------------------------------------------------------------
        */

        $sortBy =
            $validated['sort_by']
            ?? 'priority';

        $sortDirection =
            $validated[
                'sort_direction'
            ]
            ?? 'desc';

        $query->orderBy(
            $sortBy,
            $sortDirection
        );

        if (
            $sortBy !== 'name'
        ) {
            $query->orderBy(
                'name'
            );
        }

        $query->orderBy(
            'id'
        );

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $rules =
            $query->paginate(
                (int)
                (
                    $validated[
                        'per_page'
                    ]
                    ?? 15
                )
            );

        return response()->json([
            'success' => true,

            'message' =>
                'Commission rules retrieved successfully.',

            'data' =>
                CommissionRuleResource::collection(
                    $rules->getCollection()
                )->resolve(),

            'meta' => [
                'current_page' =>
                    $rules->currentPage(),

                'from' =>
                    $rules->firstItem(),

                'last_page' =>
                    $rules->lastPage(),

                'path' =>
                    $rules->path(),

                'per_page' =>
                    $rules->perPage(),

                'to' =>
                    $rules->lastItem(),

                'total' =>
                    $rules->total(),
            ],

            'links' => [
                'first' =>
                    $rules->url(1),

                'last' =>
                    $rules->url(
                        $rules->lastPage()
                    ),

                'previous' =>
                    $rules
                        ->previousPageUrl(),

                'next' =>
                    $rules
                        ->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Create a new commission rule.
     */
    public function store(
        StoreCommissionRuleRequest $request
    ): JsonResponse {
        $rule = DB::transaction(
            function () use (
                $request
            ): CommissionRule {
                $data =
                    $this->toModelData(
                        $request->validated()
                    );

                $userId =
                    $this
                        ->authenticatedUserId(
                            $request
                        );

                $data['currency'] =
                    $data['currency']
                    ?? 'RWF';

                $data['priority'] =
                    $data['priority']
                    ?? 0;

                $data['is_active'] =
                    $data['is_active']
                    ?? true;

                $data['created_by'] =
                    $userId;

                $data['updated_by'] =
                    $userId;

                return CommissionRule::create(
                    $data
                );
            }
        );

        $this->loadResourceRelations(
            $rule
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Commission rule created successfully.',

            'data' =>
                new CommissionRuleResource(
                    $rule
                ),
        ], 201);
    }

    /**
     * Display one commission rule.
     */
    public function show(
        CommissionRule $commissionRule
    ): JsonResponse {
        $this->loadResourceRelations(
            $commissionRule
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Commission rule retrieved successfully.',

            'data' =>
                new CommissionRuleResource(
                    $commissionRule
                ),
        ]);
    }

    /**
     * Update a commission rule.
     */
    public function update(
        UpdateCommissionRuleRequest $request,
        CommissionRule $commissionRule
    ): JsonResponse {
        DB::transaction(
            function () use (
                $request,
                $commissionRule
            ): void {
                $data =
                    $this->toModelData(
                        $request->validated(),
                        $commissionRule
                    );

                $data['updated_by'] =
                    $this
                        ->authenticatedUserId(
                            $request
                        );

                $commissionRule
                    ->fill(
                        $data
                    );

                $commissionRule
                    ->save();
            }
        );

        $commissionRule
            ->refresh();

        $this->loadResourceRelations(
            $commissionRule
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Commission rule updated successfully.',

            'data' =>
                new CommissionRuleResource(
                    $commissionRule
                ),
        ]);
    }

    /**
     * Activate a commission rule.
     */
    public function activate(
        Request $request,
        CommissionRule $commissionRule
    ): JsonResponse {
        if (
            $commissionRule->is_active
        ) {
            $this->loadResourceRelations(
                $commissionRule
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Commission rule is already active.',

                'data' =>
                    new CommissionRuleResource(
                        $commissionRule
                    ),
            ]);
        }

        $commissionRule
            ->forceFill([
                'is_active' => true,

                'updated_by' =>
                    $this
                        ->authenticatedUserId(
                            $request
                        ),
            ])
            ->save();

        $commissionRule
            ->refresh();

        $this->loadResourceRelations(
            $commissionRule
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Commission rule activated successfully.',

            'data' =>
                new CommissionRuleResource(
                    $commissionRule
                ),
        ]);
    }

    /**
     * Deactivate a commission rule.
     */
    public function deactivate(
        Request $request,
        CommissionRule $commissionRule
    ): JsonResponse {
        if (
            !$commissionRule->is_active
        ) {
            $this->loadResourceRelations(
                $commissionRule
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Commission rule is already inactive.',

                'data' =>
                    new CommissionRuleResource(
                        $commissionRule
                    ),
            ]);
        }

        $commissionRule
            ->forceFill([
                'is_active' => false,

                'updated_by' =>
                    $this
                        ->authenticatedUserId(
                            $request
                        ),
            ])
            ->save();

        $commissionRule
            ->refresh();

        $this->loadResourceRelations(
            $commissionRule
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Commission rule deactivated successfully.',

            'data' =>
                new CommissionRuleResource(
                    $commissionRule
                ),
        ]);
    }

    /**
     * Soft-delete a commission rule.
     */
    public function destroy(
        CommissionRule $commissionRule
    ): JsonResponse {
        $commissionRule->delete();

        return response()->json([
            'success' => true,

            'message' =>
                'Commission rule deleted successfully.',

            'data' => null,
        ]);
    }

    /**
     * Convert public IDs supplied by the API
     * into internal database foreign keys.
     *
     * @param array<string, mixed> $validated
     *
     * @return array<string, mixed>
     */
    private function toModelData(
        array $validated,
        ?CommissionRule $existingRule = null
    ): array {
        $data =
            $validated;

        $scope =
            (string) (
                $validated['scope']
                ?? $existingRule?->scope
                ?? ''
            );

        /*
        |--------------------------------------------------------------------------
        | Department public ID → database ID
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists(
                'department_public_id',
                $validated
            )
            || (
                array_key_exists(
                    'scope',
                    $validated
                )
                && $scope ===
                    CommissionRule::SCOPE_DEPARTMENT
            )
        ) {
            $publicId =
                $validated[
                    'department_public_id'
                ]
                ?? null;

            $data['department_id'] =
                $publicId === null
                    ? null
                    : Department::query()
                        ->where(
                            'public_id',
                            $publicId
                        )
                        ->value(
                            'id'
                        );
        }

        /*
        |--------------------------------------------------------------------------
        | Category public ID → database ID
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists(
                'category_public_id',
                $validated
            )
            || (
                array_key_exists(
                    'scope',
                    $validated
                )
                && $scope ===
                    CommissionRule::SCOPE_CATEGORY
            )
        ) {
            $publicId =
                $validated[
                    'category_public_id'
                ]
                ?? null;

            $data['category_id'] =
                $publicId === null
                    ? null
                    : Category::query()
                        ->where(
                            'public_id',
                            $publicId
                        )
                        ->value(
                            'id'
                        );
        }

        /*
         * API-only fields should never be passed
         * directly to the Eloquent model.
         */
        unset(
            $data[
                'department_public_id'
            ],
            $data[
                'category_public_id'
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Scope cleanup
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists(
                'scope',
                $data
            )
        ) {
            /*
             * Global:
             *
             * no department
             * no category
             */
            if (
                $scope ===
                CommissionRule::SCOPE_GLOBAL
            ) {
                $data['department_id'] =
                    null;

                $data['category_id'] =
                    null;
            }

            /*
             * Department:
             *
             * department only
             */
            if (
                $scope ===
                CommissionRule::SCOPE_DEPARTMENT
            ) {
                $data['category_id'] =
                    null;
            }

            /*
             * Category:
             *
             * category only
             */
            if (
                $scope ===
                CommissionRule::SCOPE_CATEGORY
            ) {
                $data['department_id'] =
                    null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Fixed commission cleanup
        |--------------------------------------------------------------------------
        */

        $commissionType =
            $data[
                'commission_type'
            ]
            ?? $existingRule
                ?->commission_type;

        if (
            $commissionType ===
            CommissionRule::TYPE_FIXED
        ) {
            $data['minimum_commission'] =
                null;

            $data['maximum_commission'] =
                null;
        }

        return $data;
    }

    /**
     * Load relations required by the API resource.
     */
    private function loadResourceRelations(
        CommissionRule $rule
    ): void {
        $rule->load([
            'department:id,public_id,name,slug',

            'category:id,public_id,name,slug',

            'createdBy:id,name,email',

            'updatedBy:id,name,email',
        ]);
    }

    /**
     * Get authenticated Administrator ID.
     */
    private function authenticatedUserId(
        Request $request
    ): ?int {
        $identifier =
            $request
                ->user()
                ?->getAuthIdentifier();

        return $identifier === null
            ? null
            : (int) $identifier;
    }
}