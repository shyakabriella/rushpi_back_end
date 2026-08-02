<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategorySpecificationRequest;
use App\Http\Requests\Admin\UpdateCategorySpecificationRequest;
use App\Http\Resources\Admin\CategorySpecificationResource;
use App\Models\Category;
use App\Models\CategorySpecification;
use App\Models\SpecificationDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

final class CategorySpecificationController extends Controller
{
    /**
     * Display category specification assignments.
     */
    public function index(
        Request $request,
        Category $category
    ): JsonResponse {
        $validated = $request->validate([
            'q' => [
                'nullable',
                'string',
                'max:150',
            ],

            'is_required' => [
                'nullable',
                'boolean',
            ],

            'is_filterable' => [
                'nullable',
                'boolean',
            ],

            'is_variant_attribute' => [
                'nullable',
                'boolean',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'data_type' => [
                'nullable',
                Rule::in([
                    'text',
                    'integer',
                    'decimal',
                    'boolean',
                    'select',
                    'multiselect',
                    'date',
                ]),
            ],

            'sort_by' => [
                'nullable',
                Rule::in([
                    'sort_order',
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

        $query = $category
            ->specificationAssignments()
            ->with([
                'category:id,public_id,parent_id,name,slug,is_active',

                'specificationDefinition',

                'createdBy:id,name,email',

                'updatedBy:id,name,email',
            ]);

        if (
            isset($validated['q'])
            && trim((string) $validated['q']) !== ''
        ) {
            $search = addcslashes(
                trim((string) $validated['q']),
                '\\%_'
            );

            $like = "%{$search}%";

            $query->where(
                static function (
                    Builder $searchQuery
                ) use ($like): void {
                    $searchQuery
                        ->where(
                            'label',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'help_text',
                            'like',
                            $like
                        )
                        ->orWhereHas(
                            'specificationDefinition',
                            static function (
                                Builder $definitionQuery
                            ) use ($like): void {
                                $definitionQuery
                                    ->where(
                                        'name',
                                        'like',
                                        $like
                                    )
                                    ->orWhere(
                                        'code',
                                        'like',
                                        $like
                                    )
                                    ->orWhere(
                                        'description',
                                        'like',
                                        $like
                                    );
                            }
                        );
                }
            );
        }

        if (
            array_key_exists(
                'is_required',
                $validated
            )
        ) {
            $query->where(
                'is_required',
                $request->boolean('is_required')
            );
        }

        if (
            array_key_exists(
                'is_filterable',
                $validated
            )
        ) {
            $query->where(
                'is_filterable',
                $request->boolean('is_filterable')
            );
        }

        if (
            array_key_exists(
                'is_variant_attribute',
                $validated
            )
        ) {
            $query->where(
                'is_variant_attribute',
                $request->boolean(
                    'is_variant_attribute'
                )
            );
        }

        if (
            array_key_exists(
                'is_active',
                $validated
            )
        ) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        if (isset($validated['data_type'])) {
            $query->whereHas(
                'specificationDefinition',
                static function (
                    Builder $definitionQuery
                ) use ($validated): void {
                    $definitionQuery->where(
                        'data_type',
                        $validated['data_type']
                    );
                }
            );
        }

        $sortBy = $validated['sort_by']
            ?? 'sort_order';

        $sortDirection = $validated['sort_direction']
            ?? 'asc';

        $query->orderBy(
            $sortBy,
            $sortDirection
        );

        if ($sortBy !== 'sort_order') {
            $query->orderBy('sort_order');
        }

        $query->orderBy('id');

        $assignments = $query->paginate(
            (int) ($validated['per_page'] ?? 20)
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Category specification assignments retrieved successfully.',

            'category' => [
                'public_id' =>
                    (string) $category->public_id,

                'name' =>
                    (string) $category->name,

                'slug' =>
                    (string) $category->slug,
            ],

            'data' =>
                CategorySpecificationResource::collection(
                    $assignments->getCollection()
                )->resolve(),

            'meta' => [
                'current_page' =>
                    $assignments->currentPage(),

                'from' =>
                    $assignments->firstItem(),

                'last_page' =>
                    $assignments->lastPage(),

                'path' =>
                    $assignments->path(),

                'per_page' =>
                    $assignments->perPage(),

                'to' =>
                    $assignments->lastItem(),

                'total' =>
                    $assignments->total(),
            ],

            'links' => [
                'first' =>
                    $assignments->url(1),

                'last' =>
                    $assignments->url(
                        $assignments->lastPage()
                    ),

                'previous' =>
                    $assignments->previousPageUrl(),

                'next' =>
                    $assignments->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Create a category specification assignment.
     */
    public function store(
        StoreCategorySpecificationRequest $request,
        Category $category
    ): JsonResponse {
        $definition = $request
            ->specificationDefinition();

        if (
            !$definition instanceof
            SpecificationDefinition
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'The specification definition could not be resolved.',

                'errors' => [
                    'specification_definition_public_id' => [
                        'The selected specification definition is invalid.',
                    ],
                ],
            ], 422);
        }

        $assignment = DB::transaction(
            function () use (
                $request,
                $category,
                $definition
            ): CategorySpecification {
                $data = $request->validated();

                unset(
                    $data[
                        'specification_definition_public_id'
                    ]
                );

                $userId = $this
                    ->authenticatedUserId($request);

                $data['category_id'] =
                    $category->getKey();

                $data['specification_definition_id'] =
                    $definition->getKey();

                $data['is_required'] =
                    $data['is_required'] ?? false;

                $data['is_filterable'] =
                    $data['is_filterable']
                    ?? (bool) $definition->is_filterable;

                $data['is_variant_attribute'] =
                    $data['is_variant_attribute']
                    ?? (bool) $definition
                        ->is_variant_attribute;

                $data['is_active'] =
                    $data['is_active'] ?? true;

                $data['sort_order'] =
                    $data['sort_order']
                    ?? (int) $definition->sort_order;

                $data['created_by'] = $userId;
                $data['updated_by'] = $userId;

                $this->normalizeOverrideData($data);

                return CategorySpecification::create(
                    $data
                );
            }
        );

        $this->loadAssignmentRelations(
            $assignment
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Specification assigned to category successfully.',

            'data' =>
                new CategorySpecificationResource(
                    $assignment
                ),
        ], 201);
    }

    /**
     * Display one category specification assignment.
     */
    public function show(
        Category $category,
        CategorySpecification $categorySpecification
    ): JsonResponse {
        $this->ensureAssignmentBelongsToCategory(
            $category,
            $categorySpecification
        );

        $this->loadAssignmentRelations(
            $categorySpecification
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Category specification assignment retrieved successfully.',

            'data' =>
                new CategorySpecificationResource(
                    $categorySpecification
                ),
        ]);
    }

    /**
     * Update a category specification assignment.
     */
    public function update(
        UpdateCategorySpecificationRequest $request,
        Category $category,
        CategorySpecification $categorySpecification
    ): JsonResponse {
        $this->ensureAssignmentBelongsToCategory(
            $category,
            $categorySpecification
        );

        DB::transaction(
            function () use (
                $request,
                $categorySpecification
            ): void {
                $data = $request->validated();

                $data['updated_by'] =
                    $this->authenticatedUserId(
                        $request
                    );

                $this->normalizeOverrideData($data);

                $categorySpecification->fill(
                    $data
                );

                $categorySpecification->save();
            }
        );

        $categorySpecification->refresh();

        $this->loadAssignmentRelations(
            $categorySpecification
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Category specification assignment updated successfully.',

            'data' =>
                new CategorySpecificationResource(
                    $categorySpecification
                ),
        ]);
    }

    /**
     * Activate a category specification assignment.
     */
    public function activate(
        Request $request,
        Category $category,
        CategorySpecification $categorySpecification
    ): JsonResponse {
        $this->ensureAssignmentBelongsToCategory(
            $category,
            $categorySpecification
        );

        $definition = $categorySpecification
            ->specificationDefinition()
            ->first();

        if (
            $definition === null
            || !$definition->is_active
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'This category specification cannot be activated because its reusable specification definition is inactive.',

                'errors' => [
                    'category_specification' => [
                        'Activate the reusable specification definition first.',
                    ],
                ],
            ], 409);
        }

        if ($categorySpecification->is_active) {
            return $this->stateResponse(
                $categorySpecification,
                'Category specification assignment is already active.'
            );
        }

        $categorySpecification->forceFill([
            'is_active' => true,

            'updated_by' =>
                $this->authenticatedUserId(
                    $request
                ),
        ])->save();

        return $this->stateResponse(
            $categorySpecification,
            'Category specification assignment activated successfully.'
        );
    }

    /**
     * Deactivate a category specification assignment.
     */
    public function deactivate(
        Request $request,
        Category $category,
        CategorySpecification $categorySpecification
    ): JsonResponse {
        $this->ensureAssignmentBelongsToCategory(
            $category,
            $categorySpecification
        );

        if (!$categorySpecification->is_active) {
            return $this->stateResponse(
                $categorySpecification,
                'Category specification assignment is already inactive.'
            );
        }

        $categorySpecification->forceFill([
            'is_active' => false,

            'updated_by' =>
                $this->authenticatedUserId(
                    $request
                ),
        ])->save();

        return $this->stateResponse(
            $categorySpecification,
            'Category specification assignment deactivated successfully.'
        );
    }

    /**
     * Reorder direct specification assignments belonging to a category.
     */
    public function reorder(
        Request $request,
        Category $category
    ): JsonResponse {
        $validated = $request->validate([
            'items' => [
                'required',
                'array',
                'min:1',
                'max:200',
            ],

            'items.*.public_id' => [
                'required',
                'string',
                'size:26',
                'distinct',
            ],

            'items.*.sort_order' => [
                'required',
                'integer',
                'min:0',
                'max:4294967295',
            ],
        ]);

        $publicIds = collect(
            $validated['items']
        )
            ->pluck('public_id')
            ->values();

        $assignments = CategorySpecification::query()
            ->where(
                'category_id',
                $category->getKey()
            )
            ->whereIn(
                'public_id',
                $publicIds->all()
            )
            ->get()
            ->keyBy('public_id');

        $missingPublicIds = $publicIds
            ->reject(
                static fn (string $publicId): bool =>
                    $assignments->has($publicId)
            )
            ->values();

        if ($missingPublicIds->isNotEmpty()) {
            return response()->json([
                'success' => false,

                'message' =>
                    'One or more specification assignments do not belong to this category.',

                'errors' => [
                    'items' => [
                        sprintf(
                            'Invalid assignment identifiers: %s',
                            $missingPublicIds->implode(', ')
                        ),
                    ],
                ],
            ], 422);
        }

        $userId = $this
            ->authenticatedUserId($request);

        DB::transaction(
            static function () use (
                $validated,
                $assignments,
                $userId
            ): void {
                foreach (
                    $validated['items'] as $item
                ) {
                    $assignment = $assignments->get(
                        $item['public_id']
                    );

                    if (
                        !$assignment instanceof
                        CategorySpecification
                    ) {
                        continue;
                    }

                    $assignment->forceFill([
                        'sort_order' =>
                            (int) $item['sort_order'],

                        'updated_by' =>
                            $userId,
                    ])->save();
                }
            }
        );

        $updatedAssignments = $category
            ->specificationAssignments()
            ->with([
                'category:id,public_id,parent_id,name,slug,is_active',

                'specificationDefinition',

                'createdBy:id,name,email',

                'updatedBy:id,name,email',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,

            'message' =>
                'Category specification assignments reordered successfully.',

            'data' =>
                CategorySpecificationResource::collection(
                    $updatedAssignments
                )->resolve(),
        ]);
    }

    /**
     * Safely remove a category specification assignment.
     *
     * Assignments affecting products or child-category inheritance should
     * be deactivated rather than deleted.
     */
    public function destroy(
        Category $category,
        CategorySpecification $categorySpecification
    ): JsonResponse {
        $this->ensureAssignmentBelongsToCategory(
            $category,
            $categorySpecification
        );

        if (
            $this->assignmentMayAffectCatalogData(
                $category
            )
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'This category specification assignment cannot be deleted because the category contains products or child categories. Deactivate it instead.',

                'errors' => [
                    'category_specification' => [
                        'Deactivate the assignment to preserve existing product and inherited taxonomy data.',
                    ],
                ],
            ], 409);
        }

        try {
            DB::transaction(
                static function () use (
                    $categorySpecification
                ): void {
                    $categorySpecification->delete();
                }
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'The category specification assignment could not be deleted.',

                'errors' => [
                    'category_specification' => [
                        'The assignment may still be referenced by catalog data.',
                    ],
                ],
            ], 409);
        }

        return response()->json([
            'success' => true,

            'message' =>
                'Category specification assignment deleted successfully.',

            'data' => null,
        ]);
    }

    /**
     * Ensure the assignment belongs to the category in the route.
     */
    private function ensureAssignmentBelongsToCategory(
        Category $category,
        CategorySpecification $assignment
    ): void {
        abort_unless(
            (int) $assignment->category_id
            === (int) $category->getKey(),
            404
        );
    }

    /**
     * Normalize nullable category-specific override values.
     *
     * Empty option and validation arrays clear their category override.
     *
     * @param array<string, mixed> $data
     */
    private function normalizeOverrideData(
        array &$data
    ): void {
        if (
            array_key_exists('options', $data)
            && (
                $data['options'] === []
                || $data['options'] === null
            )
        ) {
            $data['options'] = null;
        }

        if (
            array_key_exists(
                'validation_rules',
                $data
            )
            && (
                $data['validation_rules'] === []
                || $data['validation_rules'] === null
            )
        ) {
            $data['validation_rules'] = null;
        }
    }

    /**
     * Determine whether deleting the assignment could damage catalog data.
     */
    private function assignmentMayAffectCatalogData(
        Category $category
    ): bool {
        return $category
            ->products()
            ->exists()
            || $category
                ->children()
                ->exists();
    }

    /**
     * Load all relationships required by the API resource.
     */
    private function loadAssignmentRelations(
        CategorySpecification $assignment
    ): void {
        $assignment->load([
            'category:id,public_id,parent_id,name,slug,is_active',

            'specificationDefinition',

            'createdBy:id,name,email',

            'updatedBy:id,name,email',
        ]);
    }

    /**
     * Return a resource after activation-state changes.
     */
    private function stateResponse(
        CategorySpecification $assignment,
        string $message
    ): JsonResponse {
        $assignment->refresh();

        $this->loadAssignmentRelations(
            $assignment
        );

        return response()->json([
            'success' => true,

            'message' => $message,

            'data' =>
                new CategorySpecificationResource(
                    $assignment
                ),
        ]);
    }

    /**
     * Return the authenticated administrator's internal identifier.
     */
    private function authenticatedUserId(
        Request $request
    ): ?int {
        $identifier = $request
            ->user()
            ?->getAuthIdentifier();

        return $identifier === null
            ? null
            : (int) $identifier;
    }
}
