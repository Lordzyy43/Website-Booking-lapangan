<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('field_id')
                ->constrained()
                ->cascadeOnDelete();

            // Slot waktu per jam
            $table->dateTime('start_time');
            $table->dateTime('end_time');

            // Status slot
            $table->enum('status', [
                'available',
                'locked',       // sedang ditahan (checkout)
                'booked',       // sudah dibayar
                'maintenance',  // tidak bisa dipesan
            ])->default('available');

            // Batas waktu penguncian slot
            $table->dateTime('locked_until')->nullable();

            $table->timestamps();

            // Mencegah double slot di lapangan yang sama
            $table->unique(['field_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
