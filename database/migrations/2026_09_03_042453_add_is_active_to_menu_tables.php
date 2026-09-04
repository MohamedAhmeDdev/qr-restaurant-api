<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (! Schema::hasColumn('menu_items', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_available');
            }
        });

        Schema::table('modifier_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('modifier_groups', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};