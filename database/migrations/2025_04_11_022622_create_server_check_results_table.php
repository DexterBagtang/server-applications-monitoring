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
        Schema::create('server_check_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_check_id')->constrained()->onDelete('cascade');
            $table->boolean('success');
            $table->text('output')->nullable();
            $table->float('execution_time');
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->index(['server_check_id', 'executed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_check_results');
    }
};
