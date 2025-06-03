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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('path');
            $table->string('type')->comment('laravel, codeigniter, django, node, etc.');
            $table->string('framework_version')->nullable();
            $table->string('language')->comment('php, python, javascript, etc.');
            $table->string('language_version')->nullable();
            $table->string('app_url');
            $table->string('web_server')->nullable()->comment('nginx, apache, etc.');
            $table->string('database_type')->nullable()->comment('mysql, postgresql, etc.');
            $table->string('access_log_path')->nullable()->comment('Primary access log path');
            $table->string('error_log_path')->nullable()->comment('Primary error log path');
            $table->string('status')->default('unknown');
            $table->json('environment_variables')->nullable();
            $table->json('additional_settings')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_deployed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
