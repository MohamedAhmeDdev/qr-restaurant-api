<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Remove status column from staff table
        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'status')) {
                $table->dropColumn('status');
            }
        });

        // 2. Add status column to user_roles table
        Schema::table('user_roles', function (Blueprint $table) {
            $table->enum('status', ['active', 'on_leave', 'deactivated'])
                  ->default('active')
                  ->after('role_id');
        });
    }

    public function down(): void
    {
        // 1. Remove status column from user_roles table
        Schema::table('user_roles', function (Blueprint $table) {
            if (Schema::hasColumn('user_roles', 'status')) {
                $table->dropColumn('status');
            }
        });

        // 2. Re-add status column back to staff table
        Schema::table('staff', function (Blueprint $table) {
            $table->enum('status', ['active', 'on_leave', 'deactivated'])
                  ->default('active')
                  ->after('restaurant_id');
        });
    }
};