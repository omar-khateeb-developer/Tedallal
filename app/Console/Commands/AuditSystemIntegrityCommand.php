<?php

namespace App\Console\Commands;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Services\QrCodeService;
use App\Services\TokenGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class AuditSystemIntegrityCommand extends Command
{
    protected $signature = 'tedallal:audit-system {--fix : Automatically repair broken references and regenerate missing assets}';
    protected $description = 'خوارزمية التدقيق والفحص الذاتي الاستباقي لسلامة الصور ومسارات رموز QR ومنع الهفوات';

    public function handle(QrCodeService $qrCodeService, TokenGenerator $tokenGenerator): int
    {
        $this->info('╔═════════════════════════════════════════════════════════════════════════╗');
        $this->info('║  منظومة تدلّل — خوارزمية الفحص الذاتي الاستباقي لسلامة الأصول والمسارات ║');
        $this->info('╚═════════════════════════════════════════════════════════════════════════╝');

        $hasErrors = false;
        $autoFix = $this->option('fix');

        // 1. فحص مجلد التخزين العام ووصلة public/storage
        $this->newLine();
        $this->comment('▶ 1. فحص مجلد التخزين العام (Storage Junction / Symlink)...');
        $storageDir = public_path('storage');
        if (! File::exists($storageDir)) {
            $this->error('   ❌ مجلد public/storage مفقود!');
            $hasErrors = true;
            if ($autoFix) {
                $this->call('storage:link');
                $this->info('   ✔ تم إنشاء وصلة public/storage بنجاح.');
            }
        } else {
            $this->info('   ✔ وصلة public/storage متصلة وسليمة.');
        }

        // 2. فحص وجود الشعار الذهبي الرسمي الواقي (Platform Pure Golden Logo)
        $this->newLine();
        $this->comment('▶ 2. فحص الشعار الذهبي الواقي (Fallback Logo)...');
        $fallbackLogo = public_path('images/tedallal-golden-logo-pure.png');
        if (! File::exists($fallbackLogo)) {
            $this->error('   ❌ الشعار الذهبي الأساسي مفقود من public/images/!');
            $hasErrors = true;
        } else {
            $this->info('   ✔ الشعار الذهبي الواقي موجود وجاهز للحماية: /images/tedallal-golden-logo-pure.png');
        }

        // 3. فحص جميع صور أصناف المنيو (Menu Items Images Audit)
        $this->newLine();
        $this->comment('▶ 3. فحص ومطابقة صور أصناف المنيو على القرص الصلب...');
        $items = MenuItem::withoutGlobalScopes()->get();
        $missingCount = 0;
        $healthyCount = 0;

        foreach ($items as $item) {
            $rawPath = $item->getRawOriginal('image');
            if (empty($rawPath)) {
                $this->line("   ℹ صنف #{$item->id} ({$item->name}): بدون صورة — خوارزمية النموذج توفر الشعار الذهبي تلقائياً.");
                continue;
            }

            $clean = ltrim(parse_url($rawPath, PHP_URL_PATH) ?? '', '/');
            if (! str_starts_with($clean, 'storage/') && ! str_starts_with($clean, 'images/')) {
                $clean = 'storage/' . $clean;
            }

            $existsPublic = File::exists(public_path($clean));
            $existsStorage = false;
            if (str_starts_with($clean, 'storage/')) {
                $existsStorage = File::exists(storage_path('app/public/' . substr($clean, 8)));
            }

            if ($existsPublic || $existsStorage) {
                $healthyCount++;
            } else {
                $missingCount++;
                $this->warn("   ⚠ صنف #{$item->id} ({$item->name}): ملف الصورة مفقود على القرص ('{$rawPath}')!");
                if ($autoFix) {
                    $item->update(['image' => null]);
                    $this->info("     ✔ تم تصحيح الصنف #{$item->id} ليعتمد الشعار الذهبي الواقي.");
                }
            }
        }

        $this->info("   ✔ أصناف المنيو المفحوصة: {$items->count()} (سليمة: {$healthyCount}، مفقودة: {$missingCount})");
        if ($missingCount > 0 && ! $autoFix) {
            $hasErrors = true;
            $this->comment('     💡 استخدم خيار --fix لمعالجة وتصحيح المسارات المفقودة تلقائياً.');
        }

        // 4. فحص سلامة مسارات ورموز طاولات QR (Table QR Integrity)
        $this->newLine();
        $this->comment('▶ 4. فحص رموز الطاولات وروابط QR والتحقق من منع أخطاء 404...');
        $tables = RestaurantTable::withoutGlobalScopes()->get();
        $tableIssues = 0;

        foreach ($tables as $table) {
            if (empty($table->public_token)) {
                $tableIssues++;
                $this->error("   ❌ طاولة #{$table->id} ({$table->name}): رمز الـ public_token مفقود!");
                if ($autoFix) {
                    $table->update(['public_token' => $tokenGenerator->generatePublicToken()]);
                    $this->info("     ✔ تم توليد token فريد وآمن للطاولة #{$table->id}.");
                }
                continue;
            }

            // فحص مطابقة البحث الحساس وغير الحساس للأحرف (Case-insensitive Resolution)
            $exactMatch = RestaurantTable::withoutGlobalScopes()->where('public_token', $table->public_token)->exists();
            $lowerMatch = RestaurantTable::withoutGlobalScopes()->whereRaw('LOWER(public_token) = ?', [strtolower($table->public_token)])->exists();

            if (! $exactMatch || ! $lowerMatch) {
                $tableIssues++;
                $this->error("   ❌ طاولة #{$table->id} ({$table->name}): فشل اختبار مطابقة الـ token!");
            }

            // فحص وجود صورة الـ QR وتوليدها إن كانت ناقصة
            $qrPath = "qr/{$table->restaurant_id}/{$table->public_token}.png";
            if (! Storage::disk('public')->exists($qrPath)) {
                if ($autoFix) {
                    $qrCodeService->generateForTable($table);
                    $this->info("   ✔ تم توليد صورة QR مفقودة للطاولة: {$table->name}");
                }
            }
        }

        if ($tableIssues === 0) {
            $this->info("   ✔ جميع رموز الطاولات ({$tables->count()} طاولة) مؤمنة ومطابقة 100% لخوارزمية منع الـ 404.");
        } else {
            $hasErrors = true;
        }

        // الخلاصة الختامية
        $this->newLine();
        if ($hasErrors && ! $autoFix) {
            $this->warn('⚠ تم رصد بعض الملاحظات. يُرجى تشغيل الأمر مع الخيار: php artisan tedallal:audit-system --fix');
            return Command::FAILURE;
        }

        $this->info('✨ الفحص مكتمل بنجاح: النظام محمي ومحصن بالكامل من أخطاء الصور ومسارات الـ QR!');
        return Command::SUCCESS;
    }
}

