<?php
// database/migrations/xxxx_create_modifier_groups_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        //Groups (e.g., "Select Size", "Choose Crust", "Extra Toppings")
        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g., "Size", "Toppings", "Spice Level"
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('min_select')->default(0);
            $table->unsignedTinyInteger('max_select')->default(1);
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->index(['restaurant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifier_groups');
    }
};