<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\ApiController;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends ApiController
{
    public function __construct(
        private readonly BackupService $backupService
    ) {}

    public function index(): JsonResponse
    {
        $backups = $this->backupService->listBackups();

        return $this->success([
            'backups' => $backups,
            'total_count' => count($backups),
            'latest' => $backups[0] ?? null,
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $includeMedia = $request->boolean('include_media', true);

        try {
            $result = $this->backupService->createBackup($includeMedia);

            return $this->success([
                'message' => 'تم إنشاء النسخة الاحتياطية للنظام بنجاح.',
                'backup' => $result,
            ], 201);
        } catch (\Throwable $e) {
            return $this->error('فشل إنشاء النسخة الاحتياطية: ' . $e->getMessage(), 500);
        }
    }

    public function download(string $filename): BinaryFileResponse|JsonResponse
    {
        $path = $this->backupService->getBackupPath($filename);

        if (! $path) {
            return $this->error('ملف النسخة الاحتياطية غير موجود أو مساره غير صالح.', 404);
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function destroy(string $filename): JsonResponse
    {
        $deleted = $this->backupService->deleteBackup($filename);

        if (! $deleted) {
            return $this->error('تعذر العثور على النسخة المحددة أو حذفها.', 404);
        }

        return $this->success([
            'message' => 'تم حذف ملف النسخة الاحتياطية بنجاح.',
        ]);
    }
}
