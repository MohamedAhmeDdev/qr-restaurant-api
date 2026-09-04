<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            // Update or add the status enum column
            $table->enum('status', ['active', 'suspended', 'pending'])
                  ->default('active')
                  ->change();

            // Add currency code (defaulting to USD / ISO 4217 standard 3-letter code)
            $table->string('currency', 3)->default('USD')->after('status');

            // Add background image column (nullable as restaurants may not set one immediately)
            $table->string('background_image')->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['currency', 'background_image']);
            
            // Revert status type back if necessary
            $table->string('status')->change();
        });
    }
};