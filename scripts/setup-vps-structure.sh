#!/usr/bin/env bash
# ==============================================================================
# سكربت الإعداد الأولي لبنية النشر بدون توقف (Zero-Downtime Deployment Setup)
# يُشغّل هذا السكربت لمرة واحدة فقط على خادم الـ VPS
# ==============================================================================
set -e

DEPLOY_PATH="/var/www/tedallal"
EXISTING_PATH="/home/deployer/tedallal" # مسار المشروع الحالي إن وجد

echo "📁 [1/4] إنشاء مجلدات الهيكل المعماري الجديد..."
sudo mkdir -p "$DEPLOY_PATH/releases"
sudo mkdir -p "$DEPLOY_PATH/shared/storage/app/public"
sudo mkdir -p "$DEPLOY_PATH/shared/storage/framework/cache/data"
sudo mkdir -p "$DEPLOY_PATH/shared/storage/framework/sessions"
sudo mkdir -p "$DEPLOY_PATH/shared/storage/framework/views"
sudo mkdir -p "$DEPLOY_PATH/shared/storage/logs"

echo "📋 [2/4] استيراد ملف البيئة .env والمرفقات السابقة..."
if [ -f "$EXISTING_PATH/.env" ] && [ ! -f "$DEPLOY_PATH/shared/.env" ]; then
    echo "  -> تم نسخ ملف .env من $EXISTING_PATH/.env إلى $DEPLOY_PATH/shared/.env"
    sudo cp "$EXISTING_PATH/.env" "$DEPLOY_PATH/shared/.env"
fi

if [ -d "$EXISTING_PATH/storage" ]; then
    echo "  -> نقل وتحديث محتويات التخزين الحالية إلى المجلد المشترك..."
    sudo cp -rn "$EXISTING_PATH/storage/app" "$DEPLOY_PATH/shared/storage/" 2>/dev/null || true
fi

echo "🔒 [3/4] ضبط الصلاحيات الأمنية للمستخدم deployer ومجموعة www-data..."
sudo chown -R deployer:www-data "$DEPLOY_PATH"
sudo chmod -R 775 "$DEPLOY_PATH/shared/storage"

echo "🔗 [4/4] تهيئة الرابط الرمزي الأولي (Initial current symlink)..."
INITIAL_RELEASE="$DEPLOY_PATH/releases/initial"
if [ ! -d "$INITIAL_RELEASE" ] && [ -d "$EXISTING_PATH" ]; then
    sudo mkdir -p "$INITIAL_RELEASE"
    sudo cp -rn "$EXISTING_PATH"/* "$INITIAL_RELEASE/" 2>/dev/null || true
    sudo ln -nfs "$INITIAL_RELEASE" "$DEPLOY_PATH/current"
fi

echo ""
echo "=============================================================================="
echo "✅ اكتملت تهيئة بيئة النشر بنجاح على المسار: $DEPLOY_PATH"
echo "الخطوة التالية:"
echo "1. تأكد من تحديث ملف إعداد Nginx ليشير إلى: root $DEPLOY_PATH/current/public;"
echo "2. إعادة تحميل Nginx عبر: sudo nginx -t && sudo systemctl reload nginx"
echo "=============================================================================="

