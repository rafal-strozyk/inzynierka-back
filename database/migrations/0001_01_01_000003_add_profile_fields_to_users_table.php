<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin','owner','tenant'])->default('tenant')->after('id');
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address_registered', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('pesel', 11)->nullable();
            $table->text('notes')->nullable();
            $table->string('password_hash', 255)->nullable();
            $table->string('reset_token', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role','first_name','last_name','phone',
                'address_registered','city','birth_date',
                'pesel','notes','password_hash','reset_token'
            ]);
        });
    }
};
