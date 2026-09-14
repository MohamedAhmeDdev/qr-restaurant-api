<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['staff_id', 'payment_status']);
        });

        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('total_amount');
            $table->string('payment_status')->default('pending')->after('status');
        });

        Schema::table('tables', function (Blueprint $table) {
            $table->string('status')->default('available')->after('qr_code');
        });
    }
};