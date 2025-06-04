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
        Schema::create('application_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->onDelete('cascade');
            $table->integer('request_count')->default(0);
            $table->integer('error_count')->default(0)->comment('Combined 404 and 500 errors');
            $table->integer('uptime')->nullable()->comment('Application uptime in seconds');
            $table->double('response_time_avg');
            $table->double('cache_hit_ratio')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_metrics');
    }
};
