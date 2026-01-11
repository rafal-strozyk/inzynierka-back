<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')
                ->constrained('properties')
                ->cascadeOnDelete();

            $table->string('name', 100);
            $table->string('room_number', 20)->nullable();
            $table->decimal('area', 6, 2)->nullable();
            $table->decimal('rent_cost', 10, 2);

            $table->enum('status', ['wolny','zajęty','rezerwacja'])->default('wolny');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
