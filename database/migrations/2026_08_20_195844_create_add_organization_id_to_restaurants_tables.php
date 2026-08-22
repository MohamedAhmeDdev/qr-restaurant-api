<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('status')->nullable();
            
            $table->foreignId('organization_id')
                  ->after('id')
                  ->constrained('organizations')
                  ->cascadeOnDelete();

            if (Schema::hasColumn('restaurants', 'owner_id')) {
                $table->dropForeign(['owner_id']);
                $table->dropColumn('owner_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }
};