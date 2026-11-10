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

    public function buildRawPng(RestaurantTable $table, bool $allowLogo = false): string
    {
        $url = $table->publicUrl();
        $logoPath = $allowLogo ? $this->resolveRestaurantLogoPath($table) : null;

        // Optimization for simple printing and instant mobile camera reading:
        // Use ErrorCorrectionLevel::Low (7%) without center logo punchout to produce
        // large, bold, uncluttered square modules with minimal grid density.
        $errorCorrectionLevel = $logoPath ? ErrorCorrectionLevel::Medium : ErrorCorrectionLevel::Low;

        return (new Builder(
            writer: new PngWriter,
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: $errorCorrectionLevel,
            size: 360,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            logoPath: $logoPath ?? '',
            logoResizeToWidth: $logoPath ? 56 : null,
            logoPunchoutBackground: (bool) $logoPath,
        ))->build()->getString();
    }

    private function resolveRestaurantLogoPath(?RestaurantTable $table): ?string
    {
        if (! $table) {
            return null;
        }

        $table->loadMissing('restaurant');
        $restaurant = $table->restaurant;

        if (! $restaurant) {
            return null;
        }

        // Max tier or explicit custom_branding feature entitlement
        $hasCustomBranding = $restaurant->isMax() || ($restaurant->effective_features['custom_branding'] ?? false);
        if (! $hasCustomBranding || ! $restaurant->logo) {
            return null;
        }

        $relative = ltrim($restaurant->logo, '/');

        // Check public_path
        $publicCandidate = public_path($relative);
        if (file_exists($publicCandidate) && is_file($publicCandidate)) {
            return $publicCandidate;
        }

        // Check storage/app/public
        if (str_starts_with($relative, 'storage/')) {
            $storageCandidate = storage_path('app/public/' . substr($relative, 8));
            if (file_exists($storageCandidate) && is_file($storageCandidate)) {
                return $storageCandidate;
            }
        }

        return null;
    }

    public function generateBase64ForUrl(string $url, int $size = 200, ErrorCorrectionLevel $errorLevel = ErrorCorrectionLevel::Low): string
    {
        // Minimal density, chunky modules, high-contrast white margins for 80mm/58mm thermal receipts
        $png = (new Builder(
            writer: new PngWriter,
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: $errorLevel,
            size: $size,
            margin: 6,
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

