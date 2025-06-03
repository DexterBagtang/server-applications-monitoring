<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Server;
use App\Models\AgentConnection;
use Illuminate\Support\Facades\Hash;

class ServerSeeder extends Seeder
{
    public function run()
    {
        $server = Server::create([
            'name' => 'PM Production',
            'hostname' => 'PM-Production',
            'ip_address' => '192.168.234.83',
        ]);

        $server->agentConnection()->create([
//            'server_id' => $server->id,
            'auth_type' => 'password',
            'username' => 'pgc-svradmin',
            'password' => '@8755$VrPm2nd*',
            'port' => 22,
        ]);

        $server2 = Server::create([
            'name' => 'SOA Production',
            'hostname' => 'SOA-Production',
            'ip_address' => '192.168.235.85',
        ]);

        $server2->agentConnection()->create([
//            'server_id' => $server2->id,
            'auth_type' => 'password',
            'username' => 'pgc.mailmyinvoice_$',
            'password' => '$$3rG3yM@iLPGCI8755*',
            'port' => 58349,
        ]);



        $server3 = Server::create([
            'name' => 'DTR Production',
            'hostname' => 'DTR-Production',
            'ip_address' => '192.168.235.83',
        ]);

        $server3->agentConnection()->create([
//            'server_id' => $server3->id,
            'auth_type' => 'password',
            'username' => 'pgc.hrisadmin_$',
            'password' => 's3ptDtRp@ssword',
            'port' => 58349,
        ]);

        $server4 = Server::create([
            'name' => 'IMS Production',
            'hostname' => 'IMS-Production',
            'ip_address' => '192.168.234.93',
        ]);

        $server4->agentConnection()->create([
//            'server_id' => $server3->id,
            'auth_type' => 'password',
            'username' => 'pgc-svradmin',
            'password' => '1+0p$87552nd',
            'port' => 22,
        ]);

        $server5 = Server::create([
            'name' => 'Alfresco Production',
            'hostname' => 'alfresco-production',
            'ip_address' => '192.168.235.89',
        ]);

        $server5->agentConnection()->create([
//            'server_id' => $server3->id,
            'auth_type' => 'password',
            'username' => 'pgc-docuadmin',
            'password' => 'ph1lc0mD0CUmngr8755*',
            'port' => 22,
        ]);

        $server6 = Server::create([
            'name' => 'HR Alfresco Production',
            'hostname' => 'hr-alfresco-production',
            'ip_address' => '192.168.235.91',
        ]);

        $server6->agentConnection()->create([
//            'server_id' => $server3->id,
            'auth_type' => 'password',
            'username' => 'pgc-hrdocuadmin',
            'password' => '8755*Ph1lc0mH+RD0C$',
            'port' => 22,
        ]);



//        $server->applications()->create([
//            'name' => 'PM',
//            'path' => '/var/www/projectmanagement',
//            'type' => 'laravel',
//            'language' => 'php',
//            'app_url' => 'https://pm.philcom.com',
//            'status' => 'running',
//            'access_log_path' => '/var/log/nginx/access.log'
//        ]);
//
//        $server2->applications()->create([
//            'name' => 'SOA',
//            'path' => '/var/www/mailInvoice/',
//            'type' => 'laravel',
//            'language' => 'php',
//            'app_url' => 'https://mailmyinvoice.philcom.com',
//            'status' => 'running',
//            'access_log_path' => '/var/log/httpd/ssl_access_log'
//        ]);
//
//        $server3->applications()->create([
//            'name' => 'DTR',
//            'path' => '/var/www/html/dtr',
//            'type' => 'laravel',
//            'language' => 'php',
//            'app_url' => 'https://mailmyinvoice.philcom.com',
//            'status' => 'running',
//            'access_log_path' => '/var/log/httpd/access_log'
//        ]);
    }
}
