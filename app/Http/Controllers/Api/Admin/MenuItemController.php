<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\MenuItemResource;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\RestaurantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MenuItemController extends ApiController
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
    ) {}

    public function index(Request $request)
    {
        $restaurant = $this->restaurantContext->current();

        $query = MenuItem::query()
            ->where('restaurant_id', $restaurant->id)
            ->with('category')
            ->orderBy('sort_order');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        return $this->success(MenuItemResource::collection($query->get()));
    }

    public function store(Request $request)
    {
        $restaurant = $this->restaurantContext->current();

        $validated = $request->validate([
            'category_id' => ['required', 'exists:menu_categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:10240'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category = MenuCategory::query()->findOrFail($validated['category_id']);

        if ($category->restaurant_id !== $restaurant->id) {
            abort(422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = '/storage/' . $request->file('image')->store('menu', 'public');
        }

        $item = MenuItem::query()->create([
            ...$validated,
            'image' => $imagePath,
            'restaurant_id' => $restaurant->id,
        ]);

        \App\Services\ActivityLogger::log('create_item', "قام بإضافة صنف جديد للمنيو: {$item->name}");

        return $this->success(new MenuItemResource($item), 201);
    }

    public function update(Request $request, MenuItem $item)
    {
        $this->authorizeItem($item);

        $validated = $request->validate([
            'category_id' => ['sometimes', 'exists:menu_categories,id'],
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:10240'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($request->hasFile('image')) {
            if ($item->image) {
                // Safely remove the prefix without stripping characters inside the directory name
                $oldPath = preg_replace('~^/storage/~', '', $item->image);
                Storage::disk('public')->delete($oldPath);
            }
            $validated['image'] = '/storage/' . $request->file('image')->store('menu', 'public');
        }

        $item->update($validated);

        \App\Services\ActivityLogger::log('update_item', "قام بتعديل بيانات الصنف: {$item->name}");

        return $this->success(new MenuItemResource($item->fresh()));
    }

    public function destroy(MenuItem $item)
    {
        $this->authorizeItem($item);
        $itemName = $item->name;
        
        if ($item->image) {
            $oldPath = ltrim($item->image, '/storage/');
            Storage::disk('public')->delete($oldPath);
        }

        $item->delete();

        \App\Services\ActivityLogger::log('delete_item', "قام بحذف الصنف: {$itemName}");

        return response()->json(['success' => true, 'data' => null]);
    }

    private function authorizeItem(MenuItem $item): void
    {
        if ($item->restaurant_id !== $this->restaurantContext->current()->id) {
            abort(404);
        }
    }
}
