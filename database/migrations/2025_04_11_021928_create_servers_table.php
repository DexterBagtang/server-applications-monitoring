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
//        Schema::create('servers', function (Blueprint $table) {
//            $table->id();
//            $table->string('name');
//            $table->string('hostname');
//            $table->string('ip_address');
//
//            // OS Info (fetchable via /etc/os-release)
//            $table->enum('os_type', ['ubuntu', 'centos', 'almalinux', 'debian', 'rocky', 'other'])->nullable();
//            $table->string('os_version')->nullable();
//
//            // Hardware (fetchable via lscpu, free, df, lsblk)
//            $table->string('cpu_model')->nullable();
//            $table->integer('cpu_cores')->nullable();
//            $table->integer('cpu_threads')->nullable();
//            $table->integer('ram_gb')->nullable();       // From `free -g`
//            $table->integer('disk_gb')->nullable();      // From `df -BG /`
//            $table->string('disk_type')->nullable();     // SSD/HDD (from `lsblk -d -o rota`)
//
//            // Network (fetchable via ip/ifconfig)
//            $table->string('public_ip')->nullable();     // From `curl ifconfig.me`
//            $table->string('gateway')->nullable();       // From `ip route`
//            $table->string('dns_primary')->nullable();   // From `/etc/resolv.conf`
//
//            // Performance (fetchable via /proc/loadavg, uptime)
//            $table->decimal('load_15min', 5, 2)->nullable(); // 15-min avg
//            $table->timestamp('uptime_date')->nullable();     // From `uptime -p`
//
//            // Status
//            $table->boolean('is_active')->default(true);
//            $table->timestamp('last_ping_at')->nullable();
//            $table->timestamps();
//            $table->softDeletes();
//        });
        Schema::create('servers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('hostname')->nullable();
            $table->string('ip_address');
            $table->enum('os_type', ['ubuntu', 'centos', 'almalinux', 'debian', 'rocky', 'other'])->nullable();
            $table->string('os_version')->nullable();
            $table->string('cpu_model')->nullable();
            $table->integer('cpu_cores')->nullable();
            $table->integer('cpu_threads')->nullable();
            $table->integer('ram_gb')->nullable();
            $table->integer('disk_gb')->nullable();
            $table->string('disk_type')->nullable();
            $table->string('public_ip')->nullable();
            $table->string('gateway')->nullable();
            $table->string('dns_primary')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('last_ping_at')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
