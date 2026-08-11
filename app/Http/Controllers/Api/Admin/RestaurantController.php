<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\RestaurantResource;
use App\Services\RestaurantContext;
use Illuminate\Http\Request;

class RestaurantController extends ApiController
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
    ) {}

    public function show()
    {
        return $this->success(new RestaurantResource($this->restaurantContext->current()));
    }

    public function update(Request $request)
    {
        $restaurant = $this->restaurantContext->current();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        $restaurant->update($validated);
        $this->restaurantContext->forget();

        return $this->success(new RestaurantResource($restaurant->fresh()));
    }
}
