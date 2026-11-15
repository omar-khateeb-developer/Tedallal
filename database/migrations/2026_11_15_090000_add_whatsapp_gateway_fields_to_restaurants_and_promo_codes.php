<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('whatsapp_status', 30)->default('disconnected')->after('status');
            $table->string('whatsapp_phone', 30)->nullable()->after('whatsapp_status');
            $table->timestamp('whatsapp_connected_at')->nullable()->after('whatsapp_phone');
            $table->boolean('whatsapp_auto_reply_enabled')->default(false)->after('whatsapp_connected_at');
            $table->text('whatsapp_auto_reply_message')->nullable()->after('whatsapp_auto_reply_enabled');
            $table->boolean('whatsapp_expiring_reminders_enabled')->default(true)->after('whatsapp_auto_reply_message');
        });

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->boolean('is_expiring_reminded')->default(false)->after('reminders_count');
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropColumn('is_expiring_reminded');
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_status',
                'whatsapp_phone',
                'whatsapp_connected_at',
                'whatsapp_auto_reply_enabled',
                'whatsapp_auto_reply_message',
                'whatsapp_expiring_reminders_enabled',
            ]);
        });
    }
};

