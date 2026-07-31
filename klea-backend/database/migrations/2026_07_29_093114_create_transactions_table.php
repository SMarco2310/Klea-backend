<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency',4)->default('FCFA');
            $table->string('provider_tx_id')->nullable();
            $table->string('payment_method',50);
            $table->string('phone_number',30);
            $table->string('status')->default('pending');
            $table->string('error_message')->nullable();
            $table->string('environment',10)->default('live');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
