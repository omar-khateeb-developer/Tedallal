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
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('type')->default('postpaid')->after('slug'); // 'postpaid' أو 'prepaid'
            $table->unsignedTinyInteger('windows_count')->default(1)->after('type'); // عدد النوافذ
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('service_type')->default('dine_in')->after('status'); // 'dine_in' أو 'takeaway'
            $table->unsignedTinyInteger('pickup_window')->nullable()->after('service_type'); // رقم النافذة
            $table->timestamp('paid_at')->nullable()->after('pickup_window');
            $table->foreignId('paid_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['paid_by']);
            $table->dropColumn(['service_type', 'pickup_window', 'paid_at', 'paid_by']);
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['type', 'windows_count']);
        });
    }
};
