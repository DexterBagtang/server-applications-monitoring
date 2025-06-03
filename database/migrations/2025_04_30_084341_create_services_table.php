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
        Schema::create('services', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key
            $table->unsignedBigInteger('server_id'); // Foreign key to servers table
            $table->string('name'); // Name of the service (e.g., nginx, mysql)
            $table->text('description')->nullable(); // Optional description of the service
            $table->string('type'); // Type of service (e.g., web server, database)
            $table->string('status')->default('unknown'); // Current status (e.g., running, stopped)
            $table->timestamp('last_checked_at')->nullable(); // Timestamp of the last status check
            $table->timestamps(); // created_at and updated_at
            $table->softDeletes(); // deleted_at for soft deletes

            // Foreign key constraint
            $table->foreign('server_id')
                ->references('id')
                ->on('servers')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
