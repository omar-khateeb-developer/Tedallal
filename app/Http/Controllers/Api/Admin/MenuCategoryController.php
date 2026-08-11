<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\MenuCategoryResource;
use App\Models\MenuCategory;
use App\Services\RestaurantContext;
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
            ->where('restaurant_id', $restaurant->id)
            ->orderBy('sort_order')
            ->get();

        return $this->success(MenuCategoryResource::collection($categories));
    }

    public function store(Request $request)
    {
        $restaurant = $this->restaurantContext->current();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category = MenuCategory::query()->create([
            ...$validated,
            'restaurant_id' => $restaurant->id,
        ]);

        return $this->success(new MenuCategoryResource($category), 201);
    }

    public function update(Request $request, MenuCategory $category)
    {
        $this->authorizeCategory($category);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->update($validated);

        return $this->success(new MenuCategoryResource($category->fresh()));
    }

    public function destroy(MenuCategory $category)
    {
        $this->authorizeCategory($category);

        if ($category->items()->exists()) {
            return $this->error('لا يمكن حذف تصنيف يحتوي على أصناف.', 422);
        }

        $category->delete();

        return response()->json(['success' => true, 'data' => null]);
    }

    private function authorizeCategory(MenuCategory $category): void
    {
        if ($category->restaurant_id !== $this->restaurantContext->current()->id) {
            abort(404);
        }
    }
}
