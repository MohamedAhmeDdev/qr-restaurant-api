   <?php

   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Support\Facades\DB;

   return new class extends Migration
   {
       public function up(): void
       {
           // 1. Drop existing check constraint on user_roles if present
           DB::statement('ALTER TABLE "user_roles" DROP CONSTRAINT IF EXISTS "user_roles_status_check";');

           // 2. Add the updated check constraint to allow 'suspended' and 'on_leave'
           // (Adjust the allowed values to match exactly what your app needs)
           DB::statement('ALTER TABLE "user_roles" ADD CONSTRAINT "user_roles_status_check" CHECK ("status" IN (\'active\', \'pending\', \'inactive\', \'suspended\', \'on_leave\'));');
       }

       public function down(): void
       {
           // Revert check constraint back to original strict values
           DB::statement('ALTER TABLE "user_roles" DROP CONSTRAINT IF EXISTS "user_roles_status_check";');
           DB::statement('ALTER TABLE "user_roles" ADD CONSTRAINT "user_roles_status_check" CHECK ("status" IN (\'active\', \'pending\', \'inactive\'));');
       }
   };