<?php
// database/migrations/xxxx_create_modifier_options_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
       // Options inside groups
        Schema::create('modifier_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g., "Small", "Large", "Extra Cheese"
            $table->decimal('price', 10, 2)->default(0.00);
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['modifier_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifier_options');
    }
};