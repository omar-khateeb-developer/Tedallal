<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\RestaurantResource;
use App\Http\Requests\UpdateRestaurantSettingsRequest;
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

    public function update(UpdateRestaurantSettingsRequest $request)
    {
        $restaurant = $this->restaurantContext->current();
        $validated = $request->validated();

        if ($request->hasFile('navbar_image')) {
            $path = $request->file('navbar_image')->store('restaurants', 'public');
            $validated['navbar_image'] = '/storage/' . $path;
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('restaurants', 'public');
            $validated['logo'] = '/storage/' . $path;
        }

        $restaurant->update($validated);
        $this->restaurantContext->forget();

        return $this->success(new RestaurantResource($restaurant->fresh()));
    }
}
