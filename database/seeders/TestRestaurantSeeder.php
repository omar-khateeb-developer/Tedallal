<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * هذا الـ seeder قديم وتم دمج محتواه في DatabaseSeeder.
 * نُبقي عليه فقط لتجنب أي أخطاء إذا استُدعي مباشرة،
 * لكن db:seed الرئيسي يُنفّذ DatabaseSeeder تلقائياً.
 *
 * لتنفيذ جميع البيانات دفعة واحدة:
 *   php artisan migrate:fresh --seed
 *   أو:
 *   php artisan db:seed
 */
class TestRestaurantSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('⚠️  TestRestaurantSeeder مُدمَج في DatabaseSeeder الآن.');
        $this->command->info('نفّذ: php artisan db:seed  (بدون خيارات) لتشغيل DatabaseSeeder.');
        $this->command->info('أو: php artisan migrate:fresh --seed');
    }
}