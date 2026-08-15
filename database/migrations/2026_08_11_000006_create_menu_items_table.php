<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('menu_categories')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['restaurant_id', 'category_id', 'is_active', 'is_available'], 'menu_items_res_cat_active_avail_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
