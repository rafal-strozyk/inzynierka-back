<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('issues_chats', function (Blueprint $table) {
            $table->id();

            $table->foreignId('issue_id')->constrained('issues')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();

            $table->text('message');
            $table->boolean('has_attachment')->default(false);
            $table->string('file_path', 255)->nullable();
            $table->string('file_name', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues_chats');
    }
};
