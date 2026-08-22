<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            // Only add columns that don't exist yet
            if (!Schema::hasColumn('invitations', 'organization_id')) {
                $table->foreignId('organization_id')
                      ->nullable()
                      ->after('token')
                      ->constrained('organizations')
                      ->nullOnDelete();
            }

            if (!Schema::hasColumn('invitations', 'invited_by')) {
                $table->foreignId('invited_by')
                      ->nullable()
                      ->after('role_id')
                      ->constrained('users')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['invited_by']);
            
            $table->dropColumn(['organization_id', 'invited_by']);
        });
    }
};