<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\Services\QrCodeService;
use App\Services\RestaurantContext;
use Illuminate\View\View;

class TablePrintController extends Controller
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
        private readonly QrCodeService $qrCodeService,
    ) {}

    public function __invoke(): View
    {
        $restaurant = $this->restaurantContext->current();

        $tables = RestaurantTable::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(function (RestaurantTable $table) {
                return [
                    'name' => $table->name,
                    'public_url' => $table->publicUrl(),
                    'qr_base64' => base64_encode($this->qrCodeService->getPngContents($table)),
                ];
            });

        return view('admin.tables.print-qr', [
            'restaurant' => $restaurant,
            'tables' => $tables,
        ]);
    }
}
