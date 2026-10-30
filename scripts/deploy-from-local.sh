#!/usr/bin/env bash
# ==============================================================================
# سكربت النشر المباشر من الجهاز المحلي إلى الـ VPS (Local Zero-Downtime Deploy)
# يقوم بالبناء على جهازك المحلي ويرفع الناتج الجاهز للسيرفر دون إجهاد معالج الـ VPS
# الاستخدام: ./scripts/deploy-from-local.sh
# ==============================================================================
set -e

VPS_USER="deployer"
VPS_HOST="194.163.44.154"
DEPLOY_PATH="/var/www/tedallal"
RELEASE_ID=$(date +%Y%m%d%H%M%S)

echo "🚀 [1/5] بناء ملفات الواجهة محلياً على جهازك..."
npm run build

echo "📦 [2/5] إنشاء مجلد الإصدار على السيرفر: $RELEASE_ID..."
ssh "$VPS_USER@$VPS_HOST" "mkdir -p $DEPLOY_PATH/releases/$RELEASE_ID"

echo "📤 [3/5] نقل الكود الجاهز عبر Rsync (باستثناء الملفات غير اللازمة)..."
rsync -avz --delete \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='node_modules' \
    --exclude='tests' \
    --exclude='.env' \
    --exclude='storage' \
    ./ "$VPS_USER@$VPS_HOST:$DEPLOY_PATH/releases/$RELEASE_ID/"

echo "⚡ [4/5] تفعيل الإصدار الذري على السيرفر وتحديث الكاش..."
ssh "$VPS_USER@$VPS_HOST" << EOF
    set -e
    RELEASE_PATH="$DEPLOY_PATH/releases/$RELEASE_ID"

    # ربط الملفات الدائمة المشتركة
    rm -rf "\$RELEASE_PATH/storage"
    ln -nfs "$DEPLOY_PATH/shared/.env" "\$RELEASE_PATH/.env"
    ln -nfs "$DEPLOY_PATH/shared/storage" "\$RELEASE_PATH/storage"

    # تشغيل أوامر الإنتاج
    cd "\$RELEASE_PATH"
    php artisan storage:link || true
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache

    # التبديل الذري اللحظي
    ln -nfs "\$RELEASE_PATH" "$DEPLOY_PATH/current_tmp"
    mv -Tf "$DEPLOY_PATH/current_tmp" "$DEPLOY_PATH/current"

    # إعادة تحميل الخدمات
    sudo systemctl reload php8.4-fpm
    php artisan queue:restart
    sudo supervisorctl update || true
    sudo supervisorctl restart all || true

    # تنظيف الإصدارات القديمة (إبقاء آخر 4)
    cd "$DEPLOY_PATH/releases"
    ls -dt */ 2>/dev/null | tail -n +5 | xargs -I {} rm -rf "{}" || true
EOF

echo "=============================================================================="
echo "✅ تم النشر المباشر بنجاح! الموقع يعمل الآن على الإصدار: $RELEASE_ID"
echo "=============================================================================="

