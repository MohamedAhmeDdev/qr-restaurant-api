<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name'); 
            $table->string('slug'); 
            $table->string('token', 16)->unique(); // Security token for true QR invalidation/regeneration
            $table->integer('capacity')->default(2);
            $table->text('qr_code')->nullable();
            $table->enum('status', ['available', 'occupied', 'reserved', 'cleaning'])->default('available');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            // Scoped unique constraint: distinct tables per restaurant
            $table->unique(['restaurant_id', 'slug']);
            $table->index(['restaurant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};