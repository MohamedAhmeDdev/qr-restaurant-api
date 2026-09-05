<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->unsignedInteger('table_number')->nullable()->after('restaurant_id');
            $table->unique(['restaurant_id', 'table_number']);
        });
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropUnique(['restaurant_id', 'table_number']);
            $table->dropColumn('table_number');
        });
    }
};