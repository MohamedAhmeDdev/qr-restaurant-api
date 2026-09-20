<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['restaurant_id', 'name'], 'cat_rest_name_unique');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->unique(['restaurant_id', 'name'], 'menu_rest_name_unique');
        });

        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->unique(['restaurant_id', 'name'], 'mod_rest_name_unique');
        });
    
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('cat_rest_name_unique');
        });
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropUnique('menu_rest_name_unique');
        });
        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->dropUnique('mod_rest_name_unique');
        });
    }
};