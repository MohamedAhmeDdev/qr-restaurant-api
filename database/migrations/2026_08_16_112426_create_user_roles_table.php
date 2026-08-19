<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            
            // 🔑 THE KEY TO YOUR WHOLE SYSTEM 🔑
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            
            $table->timestamps();

            // A user can only hold a specific role at a specific restaurant once
            $table->unique(['user_id', 'role_id', 'restaurant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};