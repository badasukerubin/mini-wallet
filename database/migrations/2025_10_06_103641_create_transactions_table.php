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

            $table->decimal('amount', 18, 2);
            $table->decimal('commission_fee', 18, 2);
            $table->jsonb('metadata')->nullable();

            $table->foreignId('sender_id')->constrained('users')->nullOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['sender_id', 'receiver_id']);
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
