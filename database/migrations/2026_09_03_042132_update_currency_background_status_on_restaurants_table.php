<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. PostgreSQL safe change for the status enum column
        DB::statement('ALTER TABLE "restaurants" DROP CONSTRAINT IF EXISTS "restaurants_status_check";');
        DB::statement('ALTER TABLE "restaurants" ALTER COLUMN "status" TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE "restaurants" ALTER COLUMN "status" SET DEFAULT \'active\';');
        DB::statement('ALTER TABLE "restaurants" ADD CONSTRAINT "restaurants_status_check" CHECK ("status" IN (\'active\', \'suspended\', \'pending\'));');

        // 2. Add the remaining columns normally
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('status');
            $table->string('background_image')->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['currency', 'background_image']);
        });

        DB::statement('ALTER TABLE "restaurants" DROP CONSTRAINT IF EXISTS "restaurants_status_check";');
    }
};