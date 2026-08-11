<?php

namespace App\Services;

use App\Models\RestaurantTable;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;

final class QrCodeService
{
    public function generateForTable(RestaurantTable $table): string
    {
        $url = $table->publicUrl();
        $path = $this->storagePath($table);

        $result = (new Builder(
            writer: new PngWriter,
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build();

        Storage::disk('public')->put($path, $result->getString());

        return Storage::disk('public')->url($path);
    }

    public function getPngContents(RestaurantTable $table): string
    {
        $path = $this->storagePath($table);

        if (! Storage::disk('public')->exists($path)) {
            $this->generateForTable($table);
        }

        return Storage::disk('public')->get($path);
    }

    public function regenerate(RestaurantTable $table, string $newToken): string
    {
        $oldPath = $this->storagePath($table);
        Storage::disk('public')->delete($oldPath);

        $table->public_token = $newToken;

        return $this->generateForTable($table);
    }

    private function storagePath(RestaurantTable $table): string
    {
        return "qr/{$table->restaurant_id}/{$table->public_token}.png";
    }
}
