<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\MenuCategoryResource;
use App\Models\MenuCategory;
use App\Services\RestaurantContext;
use App\Http\Requests\StoreMenuCategoryRequest;
use App\Http\Requests\UpdateMenuCategoryRequest;
use Illuminate\Http\Request;

class MenuCategoryController extends ApiController
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
    ) {}

    public function index()
    {
        $restaurant = $this->restaurantContext->current();

        $categories = MenuCategory::query()
            
            ->orderBy('sort_order')
            ->get();

        return $this->success(MenuCategoryResource::collection($categories));
    }

    public function store(StoreMenuCategoryRequest $request)
    {
        $restaurant = $this->restaurantContext->current();
        $validated = $request->validated();

        $category = MenuCategory::query()->create([
            ...$validated,
            'restaurant_id' => $restaurant->id,
        ]);

        \App\Services\ActivityLogger::log('create_category', "قام بإضافة تصنيف جديد: {$category->name}");

        return $this->success(new MenuCategoryResource($category), 201);
    }

    public function update(UpdateMenuCategoryRequest $request, MenuCategory $category)
    {
        $this->authorizeCategory($category);
        $validated = $request->validated();

        $category->update($validated);

        \App\Services\ActivityLogger::log('update_category', "قام بتعديل تصنيف المنيو: {$category->name}");

        return $this->success(new MenuCategoryResource($category->fresh()));
    }

    public function destroy(MenuCategory $category)
    {
        $this->authorizeCategory($category);

        if ($category->items()->exists()) {
            return $this->error('لا يمكن حذف تصنيف يحتوي على أصناف.', 422);
        }

        $categoryName = $category->name;
        $category->delete();

        \App\Services\ActivityLogger::log('delete_category', "قام بحذف التصنيف: {$categoryName}");

        return response()->json(['success' => true, 'data' => null]);
    }

    private function authorizeCategory(MenuCategory $category): void
    {
        if ((int) $category->restaurant_id !== (int) $this->restaurantContext->current()->id) {
            abort(404);
        }
    }
}
