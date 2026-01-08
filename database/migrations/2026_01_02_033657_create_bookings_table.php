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
      Schema::create('bookings', function (Blueprint $table) {
          $table->id();

          $table->foreignId('user_id')
              ->constrained()
              ->cascadeOnDelete();

          $table->string('booking_code')->unique();

          $table->decimal('total_amount', 12, 2);

          $table->enum('payment_status', [
              'unpaid',
              'paid',
              'expired',
              'cancelled',
          ])->default('unpaid');

          $table->string('payment_token')->nullable();

          // batas waktu pembayaran
          $table->dateTime('expired_at')->nullable();

          $table->timestamps();
          $table->softDeletes();
      });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
