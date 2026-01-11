<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('owner_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('property_type', ['mieszkanie'])->default('mieszkanie');
            $table->string('name', 150);

            $table->string('street', 150);
            $table->string('street_number', 20);
            $table->string('apartment_number', 20)->nullable();
            $table->string('city', 100);

            $table->text('description')->nullable();

            $table->enum('status', ['wolna','zajęta','w_remoncie','nieaktywna'])->default('wolna');

            $table->decimal('rent_cost', 10, 2);
            $table->decimal('utilities_cost', 10, 2)->default(0);
            $table->decimal('additional_costs', 10, 2)->default(0);

            $table->decimal('area_total', 7, 2)->nullable();
            $table->unsignedTinyInteger('bathrooms_count')->nullable();
            $table->boolean('has_balcony')->default(false);
            $table->boolean('has_terrace')->default(false);
            $table->boolean('rent_by_rooms')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
