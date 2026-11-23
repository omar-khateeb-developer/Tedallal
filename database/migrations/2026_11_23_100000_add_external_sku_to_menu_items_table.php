<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (!Schema::hasColumn('menu_items', 'external_sku')) {
                $table->string('external_sku', 100)
                    ->nullable()
                    ->after('name')
                    ->comment('رمز المادة في برنامج المحاسبة مثل الأمين أو البيان');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (Schema::hasColumn('menu_items', 'external_sku')) {
                $table->dropColumn('external_sku');
            }
        });
    }
};
