<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('custom_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('public_token', 100)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
