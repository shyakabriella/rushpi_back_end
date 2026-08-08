<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class CategoryController extends Controller
{
    /**
     * Display a paginated list of categories.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $this->isAdministrator($request)) {
            return $this->forbiddenResponse();
        }

        $perPage = min(
            max((int) $request->input('per_page', 15), 1),
            100
        );

        $query = Category::query()
            ->with([
                'parent:id,public_id,name,slug',

                /*
                 * Do not type-hint this eager-load callback as Builder.
                 *
                 * Laravel may pass the actual relationship instance
                 * (HasMany) into eager-loading constraints. Restricting
                 * the parameter to Eloquent\Builder causes a TypeError
                 * as soon as a category exists and the children relation
                 * is eager loaded.
                 */
                'children' => function ($query): void {
                    $query
                        ->select([
                            'id',
                            'public_id',
                            'parent_id',
                            'name',
                            'slug',
                            'description',
                            'image_path',
                            'is_active',
                            'sort_order',
                            'created_at',
                            'updated_at',
                        ])
                        ->withCount('products')
                        ->orderBy('sort_order')
                        ->orderBy('name');
                },
            ])
            ->withCount('products');

        $search = trim(
            (string) $request->input('q')
        );

        if ($search !== '') {
            $query->where(
                function (Builder $categoryQuery) use ($search): void {
                    $categoryQuery
                        ->where(
                            'name',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'slug',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'description',
                            'like',
                            '%'.$search.'%'
                        );
                }
            );
        }

        if ($request->has('is_active')) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        /*
         * Root categories have no parent.
         *
         * Example:
         * Electronics
         * └── Computers & Laptops
         *
         * If Computers & Laptops is the root category assigned
         * directly to a department, its categories.parent_id is null.
         */
        if ($request->boolean('root_only')) {
            $query->whereNull('parent_id');
        }

        /*
         * Filter categories by their parent's public ID.
         */
        $parentPublicId = trim(
            (string) $request->input('parent')
        );

        if ($parentPublicId !== '') {
            $query->whereHas(
                'parent',
                function (Builder $parentQuery) use (
                    $parentPublicId
                ): void {
                    $parentQuery->where(
                        'public_id',
                        $parentPublicId
                    );
                }
            );
        }

        $categories = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return CategoryResource::collection($categories)
            ->additional([
                'success' => true,
                'message' =>
                    'Categories retrieved successfully.',
            ])
            ->response();
    }

    /**
     * Create a new category.
     */
    public function store(
        StoreCategoryRequest $request
    ): JsonResponse {
        try {
            $category = DB::transaction(
                function () use ($request): Category {
                    $data = $request->validated();

                    /*
                     * An empty slug means the Category model
                     * should generate it automatically.
                     */
                    if (
                        array_key_exists('slug', $data)
                        && blank($data['slug'])
                    ) {
                        unset($data['slug']);
                    }

                    return Category::query()
                        ->create($data);
                }
            );

            $category->load([
                'parent:id,public_id,name,slug',
                'children',
            ]);

            $category->loadCount('products');

            return response()->json([
                'success' => true,
                'message' =>
                    'Category created successfully.',
                'data' =>
                    new CategoryResource($category),
            ], 201);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to create the category.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Display one category.
     */
    public function show(
        Request $request,
        Category $category
    ): JsonResponse {
        if (! $this->isAdministrator($request)) {
            return $this->forbiddenResponse();
        }

        $category->load([
            'parent:id,public_id,name,slug',

            /*
             * Same rule as index(): do not type-hint this
             * eager-loading callback as Builder because Laravel
             * may pass a HasMany relationship instance.
             */
            'children' => function ($query): void {
                $query
                    ->withCount('products')
                    ->orderBy('sort_order')
                    ->orderBy('name');
            },
        ]);

        $category->loadCount('products');

        return response()->json([
            'success' => true,
            'message' =>
                'Category retrieved successfully.',
            'data' =>
                new CategoryResource($category),
        ]);
    }

    /**
     * Update an existing category.
     */
    public function update(
        UpdateCategoryRequest $request,
        Category $category
    ): JsonResponse {
        $data = $request->validated();

        if (
            array_key_exists('parent_id', $data)
            && $this->wouldCreateCircularHierarchy(
                category: $category,
                parentId: $data['parent_id']
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'The selected parent would create a circular category hierarchy.',
                'data' => null,
            ], 422);
        }

        /*
         * Keep the existing slug when an empty slug is submitted.
         * When the name changes and slug is omitted, the model
         * generates a new unique slug automatically.
         */
        if (
            array_key_exists('slug', $data)
            && blank($data['slug'])
        ) {
            unset($data['slug']);
        }

        try {
            DB::transaction(
                function () use (
                    $category,
                    $data
                ): void {
                    $category->update($data);
                }
            );

            $category->refresh();

            $category->load([
                'parent:id,public_id,name,slug',
                'children',
            ]);

            $category->loadCount('products');

            return response()->json([
                'success' => true,
                'message' =>
                    'Category updated successfully.',
                'data' =>
                    new CategoryResource($category),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to update the category.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Soft-delete a category.
     */
    public function destroy(
        Request $request,
        Category $category
    ): JsonResponse {
        if (! $this->isAdministrator($request)) {
            return $this->forbiddenResponse();
        }

        if ($category->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This category cannot be deleted while it has child categories.',
                'data' => null,
            ], 409);
        }

        if ($category->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This category cannot be deleted while products are assigned to it.',
                'data' => null,
            ], 409);
        }

        try {
            $category->delete();

            return response()->json([
                'success' => true,
                'message' =>
                    'Category deleted successfully.',
                'data' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to delete the category.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Determine whether changing the parent would create
     * a circular category relationship.
     */
    private function wouldCreateCircularHierarchy(
        Category $category,
        mixed $parentId
    ): bool {
        if ($parentId === null) {
            return false;
        }

        $parentId = (int) $parentId;

        if ($parentId === $category->id) {
            return true;
        }

        $parent = Category::query()
            ->find($parentId);

        while ($parent !== null) {
            if ($parent->id === $category->id) {
                return true;
            }

            if ($parent->parent_id === null) {
                break;
            }

            $parent = Category::query()
                ->find($parent->parent_id);
        }

        return false;
    }

    /**
     * Check whether the authenticated user is an administrator.
     */
    private function isAdministrator(
        Request $request
    ): bool {
        $user = $request->user();

        return $user !== null
            && $user->hasAnyRole([
                'admin',
                'superadmin',
            ]);
    }

    /**
     * Return a standard forbidden response.
     */
    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' =>
                'Only administrators can manage product categories.',
            'data' => null,
        ], 403);
    }
}