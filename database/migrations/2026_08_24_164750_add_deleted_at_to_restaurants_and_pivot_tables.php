<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add softDeletes to restaurants table
        Schema::table('restaurants', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurants', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Add softDeletes to pivot table if using soft deletes on pivot relationships
        Schema::table('restaurant_user', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurant_user', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('restaurant_user', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};