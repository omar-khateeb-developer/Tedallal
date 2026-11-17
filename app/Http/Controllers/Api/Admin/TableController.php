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
            ->where('restaurant_id', $restaurant->id)
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
            'pos_x' => $validated['pos_x'] ?? 50,
            'pos_y' => $validated['pos_y'] ?? 50,
            'shape' => $validated['shape'] ?? 'circle',
            'rotation' => $validated['rotation'] ?? 0,
            'capacity' => $validated['capacity'] ?? 4,
            'zone' => $validated['zone'] ?? 'main',
            'width' => $validated['width'] ?? 90,
            'height' => $validated['height'] ?? 90,
            'iot_device_id' => $validated['iot_device_id'] ?? null,
        ]);

        $this->qrCodeService->generateForTable($table);

        \App\Services\ActivityLogger::log('create_table', "قام بإضافة طاولة جديدة: {$table->name}");

        return $this->success(new RestaurantTableResource($table), 201);
    }

    public function batchLayout(Request $request)
    {
        $restaurant = $this->restaurantContext->current();
        $validated = $request->validate([
            'tables' => ['required', 'array'],
            'tables.*.id' => ['required', 'integer'],
            'tables.*.pos_x' => ['required', 'numeric'],
            'tables.*.pos_y' => ['required', 'numeric'],
            'tables.*.shape' => ['sometimes', 'string', 'in:circle,rectangle,booth,vip'],
            'tables.*.rotation' => ['sometimes', 'integer'],
            'tables.*.capacity' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'tables.*.zone' => ['sometimes', 'string', 'max:64'],
            'tables.*.width' => ['sometimes', 'numeric'],
            'tables.*.height' => ['sometimes', 'numeric'],
        ]);

        $tableIds = collect($validated['tables'])->pluck('id');
        $tables = RestaurantTable::where('restaurant_id', $restaurant->id)
            ->whereIn('id', $tableIds)
            ->get()
            ->keyBy('id');

        foreach ($validated['tables'] as $item) {
            if ($table = $tables->get($item['id'])) {
                $table->update(array_filter([
                    'pos_x' => $item['pos_x'],
                    'pos_y' => $item['pos_y'],
                    'shape' => $item['shape'] ?? null,
                    'rotation' => $item['rotation'] ?? null,
                    'capacity' => $item['capacity'] ?? null,
                    'zone' => $item['zone'] ?? null,
                    'width' => $item['width'] ?? null,
                    'height' => $item['height'] ?? null,
                ], fn($val) => ! is_null($val)));
            }
        }

        \App\Services\ActivityLogger::log('update_floor_layout', "قام بتحديث المخطط المكاني للصالة");

        return $this->success([
            'message' => 'تم حفظ المخطط المكاني بنجاح',
            'tables_count' => count($validated['tables']),
        ]);
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

        if ($table->tableSessions()->where('status', 'active')->exists()) {
            return $this->error('لا يمكن حذف طاولة توجد عليها جلسة نشطة حالياً. يرجى إخلاء الطاولة أولاً.', 422);
        }

        if ($table->orders()->exists()) {
            return $this->error('لا يمكن حذف طاولة لها طلبات.', 422);
        }

        $tableName = $table->name;

        // حذف ملف QR من storage قبل حذف السجل
        $this->qrCodeService->deleteForTable($table);

        $table->delete();

        \App\Services\ActivityLogger::log('delete_table', "قام بحذف الطاولة: {$tableName}");

        return response()->json(['success' => true, 'data' => null]);
    }

    public function regenerateToken(RestaurantTable $table)
    {
        $this->authorizeTable($table);

        $newToken = $this->tokenGenerator->generatePublicToken();
        
        // Generate new QR and delete old one FIRST before saving to DB
        $this->qrCodeService->regenerate($table, $newToken);
        
        $table->update(['public_token' => $newToken]);

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

    public function closeActiveSession(RestaurantTable $table)
    {
        $this->authorizeTable($table);

        $session = $table->activeSession;
        if (! $session) {
            return $this->error('لا توجد جلسة نشطة حالياً لهذه الطاولة.', 404);
        }

        $session->close();

        \App\Services\ActivityLogger::log('vacate_table', "قام بإنهاء الجلسة وإخلاء الطاولة: {$table->name}");

        return $this->success([
            'message' => "تم إنهاء الجلسة وإخلاء الطاولة ({$table->name}) بنجاح.",
            'table' => new RestaurantTableResource($table->fresh()),
        ]);
    }

    private function authorizeTable(RestaurantTable $table): void
    {
        $restaurant = $this->restaurantContext->current();

        if ($table->restaurant_id !== $restaurant->id) {
            abort(403, 'هذه الطاولة لا تنتمي لمطعمك.');
        }
    }
}
