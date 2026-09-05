<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop existing check constraint if present
        DB::statement('ALTER TABLE "staff" DROP CONSTRAINT IF EXISTS "staff_status_check";');

        // 2. Set column type and default
        DB::statement('ALTER TABLE "staff" ALTER COLUMN "status" TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE "staff" ALTER COLUMN "status" SET DEFAULT \'active\';');

        // 3. Add the updated check constraint for staff
        DB::statement('ALTER TABLE "staff" ADD CONSTRAINT "staff_status_check" CHECK ("status" IN (\'active\', \'on_leave\', \'deactivated\'));');
    }

    public function down(): void
    {
        // Revert check constraint back to original values
        DB::statement('ALTER TABLE "staff" DROP CONSTRAINT IF EXISTS "staff_status_check";');
        DB::statement('ALTER TABLE "staff" ALTER COLUMN "status" TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE "staff" ALTER COLUMN "status" SET DEFAULT \'active\';');
        DB::statement('ALTER TABLE "staff" ADD CONSTRAINT "staff_status_check" CHECK ("status" IN (\'active\', \'on_leave\', \'suspended\'));');
    }
};