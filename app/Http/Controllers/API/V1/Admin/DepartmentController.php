<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Http\Requests\Admin\SyncDepartmentCategoriesRequest;
use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Http\Resources\Admin\DepartmentResource;
use App\Models\Category;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class DepartmentController
    extends Controller
{
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

                'is_active' => [
                    'nullable',
                    'boolean',
                ],

                'include_categories' => [
                    'nullable',
                    'boolean',
                ],

                'per_page' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:100',
                ],
            ]);

        $query =
            Department::query()
                ->withCount(
                    'categories'
                );

        if (
            isset($validated['q'])
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

        if (
            $request->boolean(
                'include_categories'
            )
        ) {
            $query->with([
                'categories.parent:id,public_id,name,slug',
            ]);
        }

        $departments =
            $query
                ->ordered()
                ->paginate(
                    (int) (
                        $validated[
                            'per_page'
                        ] ?? 15
                    )
                )
                ->withQueryString();

        return DepartmentResource::collection(
            $departments
        )
            ->additional([
                'success' => true,

                'message' =>
                    'Departments retrieved successfully.',
            ])
            ->response();
    }

    public function store(
        StoreDepartmentRequest $request
    ): JsonResponse {
        $department =
            DB::transaction(
                function () use (
                    $request
                ): Department {
                    $data =
                        $request
                            ->validated();

                    if (
                        array_key_exists(
                            'slug',
                            $data
                        )
                        && blank(
                            $data['slug']
                        )
                    ) {
                        unset(
                            $data['slug']
                        );
                    }

                    $data['created_by'] =
                        $request
                            ->user()
                            ?->id;

                    $data['updated_by'] =
                        $request
                            ->user()
                            ?->id;

                    return Department::query()
                        ->create(
                            $data
                        );
                }
            );

        $department->loadCount(
            'categories'
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Department created successfully.',

            'data' =>
                new DepartmentResource(
                    $department
                ),
        ], 201);
    }

    public function show(
        Request $request,
        Department $department
    ): JsonResponse {
        $department->load([
            'categories.parent:id,public_id,name,slug',
        ]);

        $department->loadCount(
            'categories'
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Department retrieved successfully.',

            'data' =>
                new DepartmentResource(
                    $department
                ),
        ]);
    }

    public function update(
        UpdateDepartmentRequest $request,
        Department $department
    ): JsonResponse {
        $data =
            $request->validated();

        if (
            array_key_exists(
                'slug',
                $data
            )
            && blank(
                $data['slug']
            )
        ) {
            unset(
                $data['slug']
            );
        }

        $data['updated_by'] =
            $request
                ->user()
                ?->id;

        $department->update(
            $data
        );

        $department->refresh();

        $department->load([
            'categories.parent:id,public_id,name,slug',
        ]);

        $department->loadCount(
            'categories'
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Department updated successfully.',

            'data' =>
                new DepartmentResource(
                    $department
                ),
        ]);
    }

    public function syncCategories(
        SyncDepartmentCategoriesRequest $request,
        Department $department
    ): JsonResponse {
        $validated =
            $request->validated();

        $items =
            collect(
                $validated[
                    'categories'
                ]
            );

        $publicIds =
            $items
                ->pluck(
                    'category_public_id'
                )
                ->values()
                ->all();

        $categories =
            Category::query()
                ->whereIn(
                    'public_id',
                    $publicIds
                )
                ->get()
                ->keyBy(
                    'public_id'
                );

        $moveExisting =
            (bool) (
                $validated[
                    'move_existing'
                ] ?? false
            );

        DB::transaction(
            function () use (
                $items,
                $categories,
                $department,
                $moveExisting
            ): void {
                $sync = [];

                foreach (
                    $items
                    as $item
                ) {
                    /** @var Category $category */
                    $category =
                        $categories->get(
                            $item[
                                'category_public_id'
                            ]
                        );

                    $existingAssignment =
                        DB::table(
                            'department_category'
                        )
                            ->where(
                                'category_id',
                                $category
                                    ->getKey()
                            )
                            ->where(
                                'department_id',
                                '!=',
                                $department
                                    ->getKey()
                            )
                            ->first();

                    if (
                        $existingAssignment
                        !== null
                        && !$moveExisting
                    ) {
                        $otherDepartment =
                            Department::query()
                                ->find(
                                    (int)
                                    $existingAssignment
                                        ->department_id
                                );

                        throw ValidationException::withMessages([
                            'categories' => [
                                sprintf(
                                    'Category "%s" already belongs to department "%s". Set move_existing=true to move it.',
                                    $category
                                        ->name,
                                    $otherDepartment
                                        ?->name
                                        ?? 'another department'
                                ),
                            ],
                        ]);
                    }

                    if (
                        $existingAssignment
                        !== null
                        && $moveExisting
                    ) {
                        DB::table(
                            'department_category'
                        )
                            ->where(
                                'category_id',
                                $category
                                    ->getKey()
                            )
                            ->where(
                                'department_id',
                                '!=',
                                $department
                                    ->getKey()
                            )
                            ->delete();
                    }

                    $sync[
                        $category->getKey()
                    ] = [
                        'sort_order' =>
                            (int) (
                                $item[
                                    'sort_order'
                                ] ?? 0
                            ),

                        'is_featured' =>
                            (bool) (
                                $item[
                                    'is_featured'
                                ] ?? false
                            ),

                        'is_active' =>
                            (bool) (
                                $item[
                                    'is_active'
                                ] ?? true
                            ),
                    ];
                }

                $department
                    ->categories()
                    ->sync(
                        $sync
                    );
            }
        );

        $department->load([
            'categories.parent:id,public_id,name,slug',
        ]);

        $department->loadCount(
            'categories'
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Department categories updated successfully.',

            'data' =>
                new DepartmentResource(
                    $department
                ),
        ]);
    }

    public function destroy(
        Request $request,
        Department $department
    ): JsonResponse {
        if (
            $department
                ->categories()
                ->exists()
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'This department cannot be deleted while categories are assigned to it.',

                'data' => null,
            ], 409);
        }

        if (
            $department
                ->commissionRules()
                ->exists()
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'This department cannot be deleted while commission rules reference it.',

                'data' => null,
            ], 409);
        }

        try {
            $department->delete();

            return response()->json([
                'success' => true,

                'message' =>
                    'Department deleted successfully.',

                'data' => null,
            ]);
        } catch (
            Throwable $exception
        ) {
            report(
                $exception
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to delete the department.',

                'data' => null,
            ], 500);
        }
    }
}