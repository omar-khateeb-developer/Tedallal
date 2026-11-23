<?php

namespace App\Http\Controllers\Api\Admin;

use App\Events\TableServiceAttended;
use App\Http\Controllers\Api\ApiController;
use App\Models\TableServiceCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableServiceCallController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;

        $calls = TableServiceCall::where('restaurant_id', $restaurantId)
            ->where('status', 'pending')
            ->with(['table' => function ($q) {
                $q->select('id', 'name', 'zone', 'shape', 'operational_stage');
            }])
            ->latest()
            ->get();

        return $this->success($calls);
    }

    public function attend(Request $request, int $id): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;

        $call = TableServiceCall::where('restaurant_id', $restaurantId)
            ->with('table')
            ->findOrFail($id);

        $call->markAsAttended($request->user()->id);

        \App\Services\ActivityLogger::log('service_call_attended', "تمت تلبية نداء الخدمة ({$call->type}) للطاولة {$call->table?->name}");

        // Broadcast to live floor plan & orders screens
        broadcast(new TableServiceAttended($call));

        return $this->success([
            'message' => 'تم تسجيل تلبية النداء بنجاح.',
            'call' => $call,
        ]);
    }
}
