<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\Services\QrCodeService;
use App\Services\RestaurantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TablePrintController extends Controller
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
        private readonly QrCodeService $qrCodeService,
    ) {}

    public function __invoke(Request $request): View
    {
        $restaurant = $this->restaurantContext->current();

        $query = RestaurantTable::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('status', 'active');

        if ($request->filled('table_id')) {
            $query->where('id', $request->integer('table_id'));
        }

        $allTables = RestaurantTable::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $tables = $query->orderBy('name')
            ->get()
            ->map(function (RestaurantTable $table) {
                $publicUrl = $table->publicUrl();
                return [
                    'id' => $table->id,
                    'name' => $table->name,
                    'table_type' => $table->table_type ?? RestaurantTable::TYPE_DINE_IN,
                    'public_url' => $publicUrl,
                    'qr_base64' => base64_encode($this->qrCodeService->buildRawPng($table)),
                ];
            });

        return view('admin.tables.print-qr', [
            'restaurant' => $restaurant,
            'tables' => $tables,
            'allTables' => $allTables,
            'selectedTableId' => $request->integer('table_id'),
        ]);
    }
}
