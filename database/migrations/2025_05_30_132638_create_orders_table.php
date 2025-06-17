<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('gender', ['male', 'female']);
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('birthday')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('payment_amount', 10, 2)->nullable();
            $table->decimal('change_amount', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->enum('payment_method', ['cash', 'transfer', 'card', 'ewallet']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};