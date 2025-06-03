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
//        Schema::create('server_metrics', function (Blueprint $table) {
//            $table->id();
//            $table->foreignId('server_id')->constrained()->onDelete('cascade');
//            $table->float('cpu_usage');
//            $table->float('memory_usage');
//            $table->float('memory_total');
//            $table->float('memory_used');
//            $table->float('disk_usage');
//            $table->float('disk_total');
//            $table->float('disk_used');
//            $table->integer('process_count');
//            $table->float('load_avg_1min');
//            $table->float('load_avg_5min');
//            $table->float('load_avg_15min');
//            $table->timestamp('recorded_at');
//            $table->timestamps();
//
//            $table->index(['server_id', 'recorded_at']);
//        });
        Schema::create('server_metrics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('server_id');
            $table->double('cpu_usage')->nullable();
            $table->double('memory_total')->nullable();
            $table->double('memory_used')->nullable();
            $table->double('disk_total')->nullable();
            $table->double('disk_used')->nullable();
            $table->integer('process_count')->nullable();
            $table->double('load_avg_1min')->nullable();
            $table->double('load_avg_5min')->nullable();
            $table->double('load_avg_15min')->nullable();
            $table->timestamp('uptime_date')->nullable();
            $table->unsignedBigInteger('network_in')->nullable();
            $table->unsignedBigInteger('network_out')->nullable();
            $table->double('swap_total')->nullable();
            $table->double('swap_used')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->foreign('server_id')
                ->references('id')
                ->on('servers')
                ->onDelete('cascade');

            $table->index('server_id');
            $table->index('recorded_at');

            $table->index(['server_id', 'recorded_at']); // Composite index for common queries

        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_metrics');
    }
};
