<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('issue_files', function (Blueprint $table) {
            $table->id();

            $table->foreignId('issue_id')
                ->constrained('issues')
                ->cascadeOnDelete();

            $table->string('file_path', 255);
            $table->string('file_name', 255)->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_files');
    }
};
