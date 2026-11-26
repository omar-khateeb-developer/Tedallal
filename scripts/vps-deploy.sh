#!/bin/bash
set -e
unset GIT_DIR
cd /var/www/tedallal

echo "📥 [1/5] سحب التحديثات والأصول الجاهزة من المستودع..."
git fetch origin main
git reset --hard origin/main

echo "📦 [2/5] تثبيت حزم PHP الإنتاجية السريعة..."
composer install --no-dev --prefer-dist --optimize-autoloader --no-progress --no-interaction

echo "🎨 [2.5/5] بناء وتجميع ملفات الواجهات والأصول للإنتاج (Vite Build)..."
export NODE_OPTIONS="--max-old-space-size=1024"
npm run build

echo "📲 [2.7/5] تحديث اعتماديات بوابة الواتساب..."
if [ -d "/var/www/tedallal/whatsapp-gateway" ]; then
    npm --prefix /var/www/tedallal/whatsapp-gateway install --omit=dev --no-audit --no-fund || true
fi

echo "🗄️ [3/5] تشغيل المايجريشن وإعداد التخزين..."
php artisan migrate --force
php artisan db:seed --class=PlatformSuperAdminsSeeder --force || true
if [ "${RUN_FLAGSHIP_SEED:-0}" = "1" ] || ! php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); exit(App\Models\Restaurant::whereIn('slug', ['jamra-craft', 'dar-alyasmin'])->exists() ? 0 : 1);"; then
    php artisan db:seed --class=FlagshipRestaurantsSeeder --force
fi
php artisan storage:link || true
chmod -R 777 /var/www/tedallal/storage/app/public/qr 2>/dev/null || true
chmod 777 /var/www/tedallal/storage/logs/laravel.log 2>/dev/null || true

echo "🔒 [3.5/5] تحصين الأمان وضمان سلامة إعدادات النطاق وتطابق البيئة..."
sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' /var/www/tedallal/.env 2>/dev/null || true
sed -i 's/tedallal\.omarkhateeb\.com/tedallal.tech/g' /var/www/tedallal/.env 2>/dev/null || true

echo "⚡ [4/5] تحديث التخزين المؤقت الذكي..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "🔄 [5/5] إعادة تحميل PHP-FPM والعمال بسلاسة وبدون أي انقطاع..."
sudo systemctl reload php8.4-fpm
php artisan queue:restart
sudo supervisorctl restart tedallal-queue tedallal-reverb
sudo systemctl restart tedallal-whatsapp 2>/dev/null || true
sleep 2
curl -sf http://127.0.0.1:8082/health > /dev/null && echo "✅ بوابة الواتساب تعمل على المنفذ 8082" || echo "⚠️ تحذير: تعذر الوصول لفحص بوابة الواتساب على المنفذ 8082"

echo "✅ تم اكتمال النشر فائق السرعة بنجاح وبدون توقف!"

