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
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable()->after('is_active');
            $table->boolean('is_single_use')->default(false)->after('starts_at');
            $table->unsignedInteger('times_used')->default(0)->after('is_single_use');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'is_single_use', 'times_used']);
        });
    }
};
