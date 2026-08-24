<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Remove is_super_admin from users table
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_super_admin')) {
                $table->dropColumn('is_super_admin');
            }
        });

        // 2. Modify user_roles table (drop restaurant_id foreign key & column)
        Schema::table('user_roles', function (Blueprint $table) {
            // Drop unique key if it exists
            $table->dropUnique(['user_id', 'role_id', 'restaurant_id']);
            
            // Drop foreign key and column
            $table->dropForeign(['restaurant_id']);
            $table->dropColumn('restaurant_id');

            // Add simple unique constraint between user and role
            $table->unique(['user_id', 'role_id']);
        });

        // 3. Create new restaurant_user pivot table
        Schema::create('restaurant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'restaurant_id']);
        });
    }

    public function down(): void
    {
        // Drop new pivot table
        Schema::dropIfExists('restaurant_user');

        // Re-add restaurant_id to user_roles
        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'role_id']);
            $table->foreignId('restaurant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'role_id', 'restaurant_id']);
        });

        // Re-add is_super_admin to users
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false);
        });
    }
};