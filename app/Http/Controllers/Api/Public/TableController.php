<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\RestaurantTableResource;
use App\Models\RestaurantTable;
use Illuminate\Support\Facades\Log;

class TableController extends ApiController
{
    public function show(string $token)
    {
        $table = RestaurantTable::query()
            ->with('restaurant')
            ->where('public_token', $token)
            ->first();

        if (! $table || ! $table->isActive()) {
            Log::warning('Invalid table token accessed', ['token' => substr($token, 0, 8).'...']);

            return $this->error('الطاولة غير موجودة.', 404);
        }

        if (! $table->restaurant->isActive()) {
            return $this->error('المطعم غير متاح حالياً.', 403);
        }

        return $this->success(new RestaurantTableResource($table));
    }
}
