<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('phone', 20);
            $table->unsignedInteger('visits_count')->default(1);
            $table->text('notes')->nullable();
            $table->timestamp('last_visit_at')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'phone'], 'rest_cust_restaurant_phone_unique');
            $table->index(['restaurant_id', 'visits_count'], 'rest_cust_restaurant_visits_idx');
        });

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('restaurant_id')
                ->constrained('restaurant_customers')
                ->nullOnDelete();
            $table->string('source', 30)->default('manual')->after('code');
            $table->foreignId('free_menu_item_id')
                ->nullable()
                ->after('min_order_value')
                ->constrained('menu_items')
                ->nullOnDelete();
            $table->timestamp('reminded_at')->nullable()->after('expires_at');
            $table->unsignedInteger('reminders_count')->default(0)->after('reminded_at');
        });

        Schema::table('order_wheel_tokens', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('restaurant_id')
                ->constrained('restaurant_customers')
                ->nullOnDelete();
            $table->string('customer_name', 100)->nullable()->after('customer_id');
            $table->string('customer_phone', 20)->nullable()->after('customer_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_wheel_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['customer_name', 'customer_phone']);
        });

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('free_menu_item_id');
            $table->dropColumn(['source', 'reminded_at', 'reminders_count']);
        });

        Schema::dropIfExists('restaurant_customers');
    }
};

