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
        Schema::create('agent_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->string('auth_type');
            $table->string('username');
            $table->text('ssh_key')->nullable();
            $table->string('password')->nullable();
            $table->integer('port')->default(22);
            $table->string('agent_version')->nullable();
            $table->timestamp('last_connection_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_connections');
    }
};
