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
        Schema::table('application_metrics', function (Blueprint $table) {
            $table->integer('uptime')->nullable()->after('cache_hit_ratio')->comment('Application uptime in seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_metrics', function (Blueprint $table) {
            $table->dropColumn('uptime');
        });
    }
};
