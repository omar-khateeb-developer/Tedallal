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
        $path = $this->storagePath($table);
        $dir = dirname($path);

        if (! Storage::disk('public')->exists($dir)) {
            Storage::disk('public')->makeDirectory($dir);
        }

        $pngData = $this->buildRawPng($table);
        $saved = Storage::disk('public')->put($path, $pngData);

        if (! $saved) {
            \Illuminate\Support\Facades\Log::warning("QrCodeService: failed to write QR to public disk at {$path}");
        }

        return Storage::disk('public')->url($path);
    }

    public function buildRawPng(RestaurantTable $table): string
    {
        $url = $table->publicUrl();

        return (new Builder(
            writer: new PngWriter,
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build()->getString();
    }

    public function generateBase64ForUrl(string $url, int $size = 200): string
    {
        $png = (new Builder(
            writer: new PngWriter,
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 4,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build()->getString();

        return base64_encode($png);
    }

    public function getPngContents(RestaurantTable $table): string
    {
        $path = $this->storagePath($table);

        if (! Storage::disk('public')->exists($path)) {
            $this->generateForTable($table);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->get($path);
        }

        // Fallback: generate and return raw PNG directly in-memory if disk write failed
        return $this->buildRawPng($table);
    }

    public function deleteForTable(RestaurantTable $table): void
    {
        $path = $this->storagePath($table);
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function regenerate(RestaurantTable $table, string $newToken): string
    {
        $oldPath = $this->storagePath($table);
        $oldToken = $table->public_token;

        $table->public_token = $newToken;
        $url = $this->generateForTable($table);

        // Delete old QR only AFTER the new QR has been generated and if tokens differ
        if ($oldToken && $oldToken !== $newToken && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return $url;
    }

    private function storagePath(RestaurantTable $table): string
    {
        return "qr/{$table->restaurant_id}/{$table->public_token}.png";
    }
}

