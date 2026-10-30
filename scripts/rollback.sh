#!/usr/bin/env bash
# ==============================================================================
# سكربت التراجع الفوري إلى الإصدار السابق (Instant Rollback)
# يُشغّل على الـ VPS للعودة للإصدار السابق خلال ثانية واحدة بدون إعادة بناء
# ==============================================================================
set -e

DEPLOY_PATH="/var/www/tedallal"

if [ ! -d "$DEPLOY_PATH/releases" ]; then
    echo "❌ خطأ: مجلد الإصدارات $DEPLOY_PATH/releases غير موجود."
    exit 1
fi

cd "$DEPLOY_PATH/releases"

# الحصول على الإصدار السابق للإصدار الحالي (الإصدار الثاني في الترتيب الزمني)
PREVIOUS_RELEASE=$(ls -dt */ 2>/dev/null | sed 's#/##' | sed -n '2p')

if [ -z "$PREVIOUS_RELEASE" ]; then
    echo "❌ لا يوجد إصدار سابق مسجل للرجوع إليه!"
    exit 1
fi

echo "⏪ [1/3] جاري الرجوع الفوري للإصدار السابق: $PREVIOUS_RELEASE"

# التبديل الذري للرابط الرمزي
ln -nfs "$DEPLOY_PATH/releases/$PREVIOUS_RELEASE" "$DEPLOY_PATH/current_tmp"
mv -Tf "$DEPLOY_PATH/current_tmp" "$DEPLOY_PATH/current"

echo "⚡ [2/3] تحديث الكاش وإعادة ربط التخزين..."
cd "$DEPLOY_PATH/current"
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "🔄 [3/3] إعادة تحميل PHP-FPM والعمليات الخلفية..."
sudo systemctl reload php8.4-fpm
php artisan queue:restart
sudo supervisorctl update || true
sudo supervisorctl restart all || true

echo ""
echo "=============================================================================="
echo "✅ تم التراجع بنجاح! الموقع يعمل الآن على الإصدار: $PREVIOUS_RELEASE"
echo "=============================================================================="

