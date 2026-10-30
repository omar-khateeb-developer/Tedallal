#!/bin/bash
set -e
unset GIT_DIR
cd /var/www/tedallal

echo "📥 [1/5] سحب التحديثات والأصول الجاهزة من المستودع..."
git fetch origin main
git reset --hard origin/main

echo "📦 [2/5] تثبيت حزم PHP الإنتاجية السريعة..."
composer install --no-dev --prefer-dist --optimize-autoloader --no-progress --no-interaction

echo "🗄️ [3/5] تشغيل المايجريشن وإعداد التخزين..."
php artisan migrate --force
php artisan storage:link || true
chmod -R 777 /var/www/tedallal/storage/app/public/qr 2>/dev/null || true
chmod 777 /var/www/tedallal/storage/logs/laravel.log 2>/dev/null || true

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

echo "✅ تم اكتمال النشر فائق السرعة بنجاح وبدون توقف!"

