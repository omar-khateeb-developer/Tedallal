<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tables:generate-qr {--force : Force re-generate all QR codes even if they exist} {--clean-orphans : Delete QR files for tables that no longer exist}', function (\App\Services\QrCodeService $qrCodeService) {
    $force = $this->option('force');
    $cleanOrphans = $this->option('clean-orphans');
    $appUrl = rtrim(config('app.url'), '/');
    $this->info("🔄 بدء فحص وتوليد رموز QR للطاولات باستخدام النطاق: {$appUrl}");

    $tables = \App\Models\RestaurantTable::withoutGlobalScopes()->get();
    $generated = 0;
    $skipped = 0;
    $validPaths = [];

    foreach ($tables as $table) {
        $path = "qr/{$table->restaurant_id}/{$table->public_token}.png";
        $validPaths[$path] = true;
        $exists = \Illuminate\Support\Facades\Storage::disk('public')->exists($path);

        if (! $exists || $force) {
            $qrCodeService->generateForTable($table);
            $generated++;
            $this->line("  ✓ تم تحديث QR للطاولة #{$table->id} ({$table->name}) -> " . $table->publicUrl());
        } else {
            $skipped++;
        }
    }

    $deletedOrphans = 0;
    if ($cleanOrphans) {
        $allFiles = \Illuminate\Support\Facades\Storage::disk('public')->allFiles('qr');
        foreach ($allFiles as $file) {
            if (! isset($validPaths[$file])) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($file);
                $deletedOrphans++;
            }
        }
        $this->info("🗑️ تم حذف {$deletedOrphans} ملف QR قديم لطاولات محذوفة.");
    }

    $this->info("✅ اكتملت العملية بنجاح: تم توليد/تحديث {$generated} رمز QR (تم تخطي {$skipped} موجود مسبقاً).");
})->purpose('Generate or regenerate QR code images for all tables');
