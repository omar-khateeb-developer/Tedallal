<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\RestaurantTableResource;
use App\Models\RestaurantTable;
use App\Services\QrCodeService;
use App\Services\RestaurantContext;
use App\Services\TokenGenerator;
use App\Http\Requests\StoreTableRequest;
use App\Http\Requests\UpdateTableRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TableController extends ApiController
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
        private readonly TokenGenerator $tokenGenerator,
        private readonly QrCodeService $qrCodeService,
    ) {}

    public function index()
    {
        $restaurant = $this->restaurantContext->current();

        $tables = RestaurantTable::query()
            
            ->orderBy('name')
            ->get();

        return $this->success(RestaurantTableResource::collection($tables));
    }

    public function store(StoreTableRequest $request)
    {
        $restaurant = $this->restaurantContext->current();
        $validated = $request->validated();

        $table = RestaurantTable::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => $validated['name'],
            'public_token' => $this->tokenGenerator->generatePublicToken(),
            'status' => $validated['status'] ?? 'active',
        ]);

        $this->qrCodeService->generateForTable($table);

        \App\Services\ActivityLogger::log('create_table', "قام بإضافة طاولة جديدة: {$table->name}");

        return $this->success(new RestaurantTableResource($table), 201);
    }

    public function update(UpdateTableRequest $request, RestaurantTable $table)
    {
        $this->authorizeTable($table);
        $validated = $request->validated();

        $table->update($validated);

        \App\Services\ActivityLogger::log('update_table', "قام بتعديل بيانات الطاولة: {$table->name}");

        return $this->success(new RestaurantTableResource($table->fresh()));
    }

    public function destroy(RestaurantTable $table)
    {
        $this->authorizeTable($table);

        if ($table->orders()->exists()) {
            return $this->error('لا يمكن حذف طاولة لها طلبات.', 422);
        }

        $tableName = $table->name;
        $table->delete();

        \App\Services\ActivityLogger::log('delete_table', "قام بحذف الطاولة: {$tableName}");

        return response()->json(['success' => true, 'data' => null]);
    }

    public function regenerateToken(RestaurantTable $table)
    {
        $this->authorizeTable($table);

        $newToken = $this->tokenGenerator->generatePublicToken();
        $table->update(['public_token' => $newToken]);
        $this->qrCodeService->regenerate($table, $newToken);

        \App\Services\ActivityLogger::log('update_table', "قام بتوليد رمز QR جديد للطاولة: {$table->name}");

        return $this->success(new RestaurantTableResource($table->fresh()));
    }

    public function qr(RestaurantTable $table): Response
    {
        $this->authorizeTable($table);

        return response($this->qrCodeService->getPngContents($table), 200, [
            'Content-Type' => 'image/png',
        ]);
    }

    private function authorizeTable(RestaurantTable $table): void
    {
        $restaurant = $this->restaurantContext->current();

        if ($table->restaurant_id !== $restaurant->id) {
            abort(404);
        }
    }
}
