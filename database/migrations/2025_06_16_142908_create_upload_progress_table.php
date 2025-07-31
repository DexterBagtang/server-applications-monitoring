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
        Schema::create('upload_progress', function (Blueprint $table) {
            $table->id();
            $table->string('progress_key')->unique();
            $table->unsignedBigInteger('server_id');
            $table->string('local_path'); // Path to the local file being uploaded
            $table->string('remote_path'); // Destination path on the remote server
            $table->string('original_filename'); // Original filename of the uploaded file
            $table->decimal('uploaded_mb', 10, 2)->default(0);
            $table->decimal('total_size_mb', 10, 2)->nullable();
            $table->enum('status', ['pending', 'uploading', 'complete', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
            $table->index(['server_id', 'status']);
            $table->index('progress_key');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upload_progress');
    }
};
