<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rename table from restaurant_user to staff
        Schema::rename('restaurant_user', 'staff');

        // 2. Add status and shift columns to staff table
        Schema::table('staff', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'on_leave'])->default('active')->after('restaurant_id');
            $table->enum('shift_type', ['day', 'night', 'full_time', 'flexible'])->default('day')->after('status');
        });
    }

    public function down(): void
    {
        // 1. Drop added columns
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['status', 'shift_type']);
        });

        // 2. Revert table name back
        Schema::rename('staff', 'restaurant_user');
    }
};