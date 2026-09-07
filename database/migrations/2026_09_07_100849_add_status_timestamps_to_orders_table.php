<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('customer_note');
            $table->timestamp('preparing_at')->nullable()->after('accepted_at');
            $table->timestamp('ready_at')->nullable()->after('preparing_at');
            $table->timestamp('served_at')->nullable()->after('ready_at');
            $table->timestamp('cancelled_at')->nullable()->after('served_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'preparing_at', 'ready_at', 'served_at', 'cancelled_at']);
        });
    }
};
