<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\TestController;
use App\Models\Server;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use phpseclib3\File\ANSI;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

//Route::get('/testing',function(){
//   $servers = Server::all();
//
//   foreach ($servers as $server){
//       echo Carbon::parse($server->uptime_date)
//           ->diffForHumans(
//               syntax: Carbon::DIFF_ABSOLUTE,
//               parts:4,
//           );
//   }
//});

Route::resource('servers', ServerController::class);
Route::get('/servers/{server}/fetch',[ServerController::class,'fetch'])->name('servers.fetch');
Route::post('/servers/{server}/execute',[ServerController::class,'execute'])->name('servers.execute');
// Terminal routes
Route::middleware(['auth'])->group(function () {
    Route::post('/servers/{server}/terminal/connect', [TerminalController::class, 'connect'])->name('servers.terminal.connect');
    Route::post('/servers/{server}/terminal/execute', [TerminalController::class, 'execute'])->name('servers.terminal.execute');
    Route::post('/servers/{server}/terminal/disconnect', [TerminalController::class, 'disconnect'])->name('servers.terminal.disconnect');
});

Route::resource('applications', ApplicationController::class);
Route::get('/applications/{application}/fetch-logs',[ApplicationController::class,'fetchLogs'])->name('application.fetch.logs');


Route::resource('services', ServiceController::class);
Route::get('/services/{server}/fetch',[ServiceController::class,'fetch'])->name('services.fetch');

Route::get('reverb_test',function (){
   return \App\Events\TestReverb::dispatch();
});


Route::get('/ssh-test/{server}', [App\Http\Controllers\TestController::class, 'sshTest']);

Route::get('/sftp-test/{server}', [App\Http\Controllers\TestController::class, 'sftpTest']);


//Route::get('/mysql-dump/{server}/{database}', function (Server $server, $database) {
//    // Validate database name to prevent injection
//    if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
//        return response()->json([
//            'error' => 'Invalid database name'
//        ], 400);
//    }
//
//    // Step 1: SSH Connection for mysqldump
//    $ssh = new SSH2($server->ip_address, $server->agentConnection->port ?? 22);
//
//    if (!$ssh->login(
//        $server->agentConnection->username,
//        $server->agentConnection->password
//    )) {
//        return response()->json([
//            'error' => 'SSH login failed'
//        ], 401);
//    }
//
//    $password = $server->agentConnection->password;
//    $timestamp = date('Y-m-d_H-i-s');
//    $dumpFileName = "{$database}_dump_{$timestamp}.sql";
//    $dumpPath = "/tmp/{$dumpFileName}";
//
//    // Execute mysqldump command
//    // Note: You may need to adjust MySQL credentials based on your setup
////    $mysqldumpCommand = "mysqldump -u root -p $database > $dumpPath 2>&1 && echo 'SUCCESS' || echo 'FAILED'";
//    $mysqldumpCommand = "mysqldump -u root_admin -p '*PM!db@dm1N@8755*!' $database > $dumpPath 2>&1 && echo 'SUCCESS' || echo 'FAILED'";
//
//    $dumpResult = $ssh->exec($mysqldumpCommand);
//
////    if (strpos($dumpResult, 'SUCCESS') === false) {
////        return response()->json([
////            'error' => 'MySQL dump failed',
////            'output' => $dumpResult
////        ], 500);
////    }
//
//
//    // Check if dump file exists and get its size
//    $checkFileCommand = "ls -la {$dumpPath} 2>/dev/null || echo 'FILE_NOT_FOUND'";
//    $fileCheck = $ssh->exec($checkFileCommand);
//
//    if (strpos($fileCheck, 'FILE_NOT_FOUND') !== false) {
//        return response()->json([
//            'error' => 'Dump file was not created'
//        ], 500);
//    }
//
//    $ssh->disconnect();
//
//    // Step 2: SFTP Connection to retrieve the dump file
//    $sftp = new SFTP($server->ip_address, $server->agentConnection->port ?? 22);
//
//    if (!$sftp->login(
//        $server->agentConnection->username,
//        $server->agentConnection->password
//    )) {
//        // Clean up the dump file before returning error
////        $ssh->exec("rm -f {$dumpPath}");
//        return response()->json([
//            'error' => 'SFTP login failed'
//        ], 500);
//    }
//
//    // Get the dump file contents
//    $fileContents = $sftp->get($dumpPath);
//
//    $sftp->disconnect();
//
//    $ssh = new SSH2($server->ip_address, $server->agentConnection->port ?? 22);
//
//    if (!$ssh->login(
//        $server->agentConnection->username,
//        $server->agentConnection->password
//    )) {
//        return response()->json([
//            'error' => 'SSH login failed'
//        ], 401);
//    }
//
//    if ($fileContents === false) {
//        // Clean up the dump file before returning error
//        $ssh->exec("rm -f {$dumpPath}");
//        return response()->json([
//            'error' => 'Failed to fetch dump file via SFTP'
//        ], 500);
//    }
//
//    // Clean up the temporary dump file on the server
//    $ssh->exec("rm -f {$dumpPath}");
//
//    // Return the SQL dump as a downloadable file
//    return response($fileContents)
//        ->header('Content-Type', 'application/sql')
//        ->header('Content-Disposition', "attachment; filename=\"{$dumpFileName}\"")
//        ->header('Content-Length', strlen($fileContents));
//});



Route::post('/mysql-dump/{server}/{database}', [App\Http\Controllers\TestController::class, 'mysqlDump']);
Route::post('/database-dump/{server}/{database}', [BackupController::class, 'databaseDump']);

//Route::get('/download-zip/{server}',[App\Http\Controllers\TestController::class,'downloadZip']);
//Route::get('/check-download-progress',[App\Http\Controllers\TestController::class,'checkDownloadProgress']);
//
//Route::get('/download/progress/{key}', [TestController::class, 'getDownloadProgress'])->name('download.progress');
//Route::get('/download/file/{filename}', [TestController::class, 'downloadFile'])->name('download.file');

Route::prefix('downloads')->group(function () {
    // Start a download
    Route::post('/servers/{server}/download', [DownloadController::class, 'downloadZip'])
        ->name('server.download');

    // Get progress by progress key
    Route::get('/progress/{key}', [DownloadController::class, 'getDownloadProgress'])
        ->name('download.progress');

    // Get progress by ID
    Route::get('/progress/id/{id}', [DownloadController::class, 'getDownloadProgressById'])
        ->name('download.progress.by.id');

    // Download completed file
    Route::get('/file/{filename}', [DownloadController::class, 'downloadFile'])
        ->name('download.file');

    // List all downloads for a server
    Route::get('/servers/{server}', [DownloadController::class, 'listServerDownloads'])
        ->name('server.downloads.list');

    // Cancel a download
    Route::post('/cancel/{key}', [DownloadController::class, 'cancelDownload'])
        ->name('download.cancel');

    // Delete a download
    Route::delete('/{key}', [DownloadController::class, 'deleteDownload'])
        ->name('download.delete');

    Route::get('/servers/{server}/browse', [DownloadController::class, 'browseFiles'])
        ->name('server.browse.files');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
