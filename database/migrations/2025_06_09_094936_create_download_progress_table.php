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
        Schema::create('download_progress', function (Blueprint $table) {
            $table->id();
            $table->string('progress_key')->unique();
            $table->unsignedBigInteger('server_id');
            $table->string('remote_path');
            $table->string('local_filename');
            $table->decimal('downloaded_mb', 10, 2)->default(0);
            $table->decimal('total_size_mb', 10, 2)->nullable();
            $table->enum('status', ['pending', 'downloading', 'complete', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
            $table->index(['server_id', 'status']);
            $table->index('progress_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('download_progresses');
    }
};
