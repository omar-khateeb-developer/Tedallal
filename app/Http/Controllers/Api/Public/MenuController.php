<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\MenuCategoryResource;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\RestaurantContext;

class MenuController extends ApiController
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
    ) {}

    public function index()
    {
        $restaurant = $this->restaurantContext->current();

        $categories = MenuCategory::query()
            
            ->active()
            ->orderBy('sort_order')
            ->with(['activeItems' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        return $this->success(MenuCategoryResource::collection($categories));
    }

    public function categories()
    {
        $restaurant = $this->restaurantContext->current();

        $categories = MenuCategory::query()
            
            ->active()
            ->orderBy('sort_order')
            ->get();

        return $this->success(MenuCategoryResource::collection($categories));
    }

    public function items()
    {
        $restaurant = $this->restaurantContext->current();

        $items = MenuItem::query()
            
            ->orderable()
            ->orderBy('sort_order')
            ->get();

        return $this->success($items);
    }
}
