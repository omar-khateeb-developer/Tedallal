<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tables:generate-qr', function (\App\Services\QrCodeService $qrCodeService) {
    $tables = \App\Models\RestaurantTable::withoutGlobalScopes()->get();
    $generated = 0;
    foreach ($tables as $table) {
        $path = "qr/{$table->restaurant_id}/{$table->public_token}.png";
        if (! \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            $qrCodeService->generateForTable($table);
            $generated++;
            $this->info("✓ تم توليد QR للطاولة #{$table->id} ({$table->name})");
        }
    }
    $this->info("اكتمل التوليد. تم إنشاء {$generated} رمز QR مفقود.");
})->purpose('Generate missing QR code images for all tables');
