<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Admin;

use App\Enums\SpecificationDataType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSpecificationDefinitionRequest;
use App\Http\Requests\Admin\UpdateSpecificationDefinitionRequest;
use App\Http\Resources\Admin\SpecificationDefinitionResource;
use App\Models\SpecificationDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

final class SpecificationDefinitionController extends Controller
{
    /**
     * Display a paginated list of specification definitions.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => [
                'nullable',
                'string',
                'max:150',
            ],

            'data_type' => [
                'nullable',
                Rule::enum(
                    SpecificationDataType::class
                ),
            ],

            'is_active' => [
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

            'assigned' => [
                'nullable',
                'boolean',
            ],

            'sort_by' => [
                'nullable',
                Rule::in([
                    'name',
                    'code',
                    'data_type',
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

        $query = SpecificationDefinition::query()
            ->withCount('categorySpecifications')
            ->with([
                'createdBy:id,name,email',
                'updatedBy:id,name,email',
            ]);

        if (
            isset($validated['q'])
            && trim((string) $validated['q']) !== ''
        ) {
            $query->search(
                (string) $validated['q']
            );
        }

        if (isset($validated['data_type'])) {
            $query->where(
                'data_type',
                $validated['data_type']
            );
        }

        if (array_key_exists('is_active', $validated)) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        if (array_key_exists('is_filterable', $validated)) {
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

        if (array_key_exists('assigned', $validated)) {
            $assigned = $request->boolean('assigned');

            $assigned
                ? $query->has('categorySpecifications')
                : $query->doesntHave(
                    'categorySpecifications'
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

        if ($sortBy !== 'name') {
            $query->orderBy('name');
        }

        $query->orderBy('id');

        $definitions = $query->paginate(
            (int) ($validated['per_page'] ?? 15)
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Specification definitions retrieved successfully.',

            'data' =>
                SpecificationDefinitionResource::collection(
                    $definitions->getCollection()
                )->resolve(),

            'meta' => [
                'current_page' =>
                    $definitions->currentPage(),

                'from' =>
                    $definitions->firstItem(),

                'last_page' =>
                    $definitions->lastPage(),

                'path' =>
                    $definitions->path(),

                'per_page' =>
                    $definitions->perPage(),

                'to' =>
                    $definitions->lastItem(),

                'total' =>
                    $definitions->total(),
            ],

            'links' => [
                'first' =>
                    $definitions->url(1),

                'last' =>
                    $definitions->url(
                        $definitions->lastPage()
                    ),

                'previous' =>
                    $definitions->previousPageUrl(),

                'next' =>
                    $definitions->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Store a new reusable specification definition.
     */
    public function store(
        StoreSpecificationDefinitionRequest $request
    ): JsonResponse {
        $definition = DB::transaction(
            function () use ($request): SpecificationDefinition {
                $data = $request->validated();

                $userId = $this->authenticatedUserId(
                    $request
                );

                $data['is_filterable'] =
                    $data['is_filterable'] ?? false;

                $data['is_variant_attribute'] =
                    $data['is_variant_attribute'] ?? false;

                $data['is_active'] =
                    $data['is_active'] ?? true;

                $data['sort_order'] =
                    $data['sort_order'] ?? 0;

                $data['created_by'] = $userId;
                $data['updated_by'] = $userId;

                return SpecificationDefinition::create(
                    $data
                );
            }
        );

        $definition->loadCount(
            'categorySpecifications'
        );

        $definition->load([
            'createdBy:id,name,email',
            'updatedBy:id,name,email',
        ]);

        return response()->json([
            'success' => true,

            'message' =>
                'Specification definition created successfully.',

            'data' =>
                new SpecificationDefinitionResource(
                    $definition
                ),
        ], 201);
    }

    /**
     * Display one specification definition.
     */
    public function show(
        SpecificationDefinition $specificationDefinition
    ): JsonResponse {
        $specificationDefinition->loadCount(
            'categorySpecifications'
        );

        $specificationDefinition->load([
            'categorySpecifications' =>
                static function (
                    Builder $query
                ): void {
                    $query
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },

            'categorySpecifications.category:id,public_id,name,slug',

            'createdBy:id,name,email',

            'updatedBy:id,name,email',
        ]);

        return response()->json([
            'success' => true,

            'message' =>
                'Specification definition retrieved successfully.',

            'data' =>
                new SpecificationDefinitionResource(
                    $specificationDefinition
                ),
        ]);
    }

    /**
     * Update a reusable specification definition.
     */
    public function update(
        UpdateSpecificationDefinitionRequest $request,
        SpecificationDefinition $specificationDefinition
    ): JsonResponse {
        $data = $request->validated();

        $protectionError = $this
            ->destructiveUpdateProtectionMessage(
                $specificationDefinition,
                $data
            );

        if ($protectionError !== null) {
            return response()->json([
                'success' => false,

                'message' => $protectionError,

                'errors' => [
                    'specification_definition' => [
                        $protectionError,
                    ],
                ],
            ], 409);
        }

        DB::transaction(
            function () use (
                $request,
                $specificationDefinition,
                $data
            ): void {
                $data['updated_by'] =
                    $this->authenticatedUserId(
                        $request
                    );

                $specificationDefinition->fill(
                    $data
                );

                $specificationDefinition->save();
            }
        );

        $specificationDefinition->refresh();

        $specificationDefinition->loadCount(
            'categorySpecifications'
        );

        $specificationDefinition->load([
            'categorySpecifications' =>
                static function (
                    Builder $query
                ): void {
                    $query
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },

            'categorySpecifications.category:id,public_id,name,slug',

            'createdBy:id,name,email',

            'updatedBy:id,name,email',
        ]);

        return response()->json([
            'success' => true,

            'message' =>
                'Specification definition updated successfully.',

            'data' =>
                new SpecificationDefinitionResource(
                    $specificationDefinition
                ),
        ]);
    }

    /**
     * Deactivate a specification definition.
     *
     * Deactivation is allowed even when the definition is assigned to
     * categories. It prevents the definition from being used in active
     * product forms without destroying historical taxonomy records.
     */
    public function deactivate(
        Request $request,
        SpecificationDefinition $specificationDefinition
    ): JsonResponse {
        if (!$specificationDefinition->is_active) {
            return $this->stateResponse(
                $specificationDefinition,
                'Specification definition is already inactive.'
            );
        }

        $specificationDefinition->forceFill([
            'is_active' => false,

            'updated_by' =>
                $this->authenticatedUserId(
                    $request
                ),
        ])->save();

        return $this->stateResponse(
            $specificationDefinition,
            'Specification definition deactivated successfully.'
        );
    }

    /**
     * Reactivate a specification definition.
     */
    public function activate(
        Request $request,
        SpecificationDefinition $specificationDefinition
    ): JsonResponse {
        if ($specificationDefinition->is_active) {
            return $this->stateResponse(
                $specificationDefinition,
                'Specification definition is already active.'
            );
        }

        $specificationDefinition->forceFill([
            'is_active' => true,

            'updated_by' =>
                $this->authenticatedUserId(
                    $request
                ),
        ])->save();

        return $this->stateResponse(
            $specificationDefinition,
            'Specification definition activated successfully.'
        );
    }

    /**
     * Safely delete an unused specification definition.
     *
     * Assigned definitions must be deactivated instead of deleted.
     */
    public function destroy(
        SpecificationDefinition $specificationDefinition
    ): JsonResponse {
        if (
            $specificationDefinition
                ->isAssignedToCategories()
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'This specification definition cannot be deleted because it is assigned to one or more categories. Deactivate it instead.',

                'errors' => [
                    'specification_definition' => [
                        'Remove all category assignments or deactivate the definition before deleting it.',
                    ],
                ],
            ], 409);
        }

        try {
            DB::transaction(
                static function () use (
                    $specificationDefinition
                ): void {
                    $specificationDefinition->delete();
                }
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'The specification definition could not be deleted.',

                'errors' => [
                    'specification_definition' => [
                        'The definition may still be referenced by another catalog record.',
                    ],
                ],
            ], 409);
        }

        return response()->json([
            'success' => true,

            'message' =>
                'Specification definition deleted successfully.',

            'data' => null,
        ]);
    }

    /**
     * Protect assigned definitions from destructive updates.
     *
     * Changing the machine code would disconnect product JSON values from
     * their taxonomy definition. Changing the data type could make existing
     * values invalid. Assigned definitions must therefore retain both.
     *
     * @param array<string, mixed> $data
     */
    private function destructiveUpdateProtectionMessage(
        SpecificationDefinition $definition,
        array $data
    ): ?string {
        if (
            !$definition->isAssignedToCategories()
        ) {
            return null;
        }

        if (
            array_key_exists('code', $data)
            && (string) $data['code']
                !== (string) $definition->code
        ) {
            return
                'The code of an assigned specification definition cannot be changed. Create a new definition or remove its category assignments first.';
        }

        if (
            array_key_exists('data_type', $data)
            && (string) $data['data_type']
                !== $definition->data_type->value
        ) {
            return
                'The data type of an assigned specification definition cannot be changed. Create a new definition or remove its category assignments first.';
        }

        return null;
    }

    /**
     * Return a refreshed resource after an activation-state change.
     */
    private function stateResponse(
        SpecificationDefinition $definition,
        string $message
    ): JsonResponse {
        $definition->refresh();

        $definition->loadCount(
            'categorySpecifications'
        );

        $definition->load([
            'createdBy:id,name,email',
            'updatedBy:id,name,email',
        ]);

        return response()->json([
            'success' => true,

            'message' => $message,

            'data' =>
                new SpecificationDefinitionResource(
                    $definition
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
