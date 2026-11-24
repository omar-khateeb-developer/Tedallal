<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class TedallalBackupCommand extends Command
{
    protected $signature = 'tedallal:backup {--no-media : Backup database only without storage media}';

    protected $description = 'إنشاء نسخة احتياطية آمنة وشاملة لمنصة تدلّل (قاعدة البيانات + الوسائط)';

    public function handle(BackupService $backupService): int
    {
        $includeMedia = ! $this->option('no-media');

        $this->info('📦 بدء إنشاء النسخة الاحتياطية لمنصة تدلّل...');
        $this->line($includeMedia ? '   ✓ تشمل قاعدة البيانات وملفات الوسائط/QR' : '   ✓ تشمل قاعدة البيانات فقط (--no-media)');

        try {
            $result = $backupService->createBackup($includeMedia);

            $this->newLine();
            $this->info('✅ تم إنشاء النسخة الاحتياطية بنجاح!');
            $this->table(
                ['البند', 'القيمة'],
                [
                    ['اسم الملف', $result['filename']],
                    ['الحجم الإجمالي', $result['size_formatted']],
                    ['الجداول المنسوخة', $result['tables_count']],
                    ['الصفوف المحفوظة', number_format($result['rows_count'])],
                    ['ملفات الوسائط', number_format($result['media_files_count'])],
                    ['المدة المستغرقة', $result['duration_seconds'] . ' ثانية'],
                    ['المسار', $result['path']],
                ]
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ فشل إنشاء النسخة الاحتياطية: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error('Backup Command Failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return Command::FAILURE;
        }
    }
}
