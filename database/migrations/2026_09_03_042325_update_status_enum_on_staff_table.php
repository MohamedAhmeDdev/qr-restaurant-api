<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->enum('status', ['active', 'on_leave', 'deactivated'])
                  ->default('active')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->enum('status', ['active', 'on_leave', 'suspended'])
                  ->default('active')
                  ->change();
        });
    }
};