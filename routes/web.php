<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\UploadController;
use App\Models\Server;
use App\Services\SSHService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use phpseclib3\File\ANSI;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

use Illuminate\Support\Facades\Storage;


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
//Route::post('/servers/{server}/execute',[ServerController::class,'execute'])->name('servers.execute');
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

Route::post('/mysql-dump/{server}/{database}', [App\Http\Controllers\TestController::class, 'mysqlDump']);
Route::post('/database-dump/{server}/{database}', [BackupController::class, 'databaseDump']);

Route::post('/database-backup/{application}', [BackupController::class, 'databaseBackup']);

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

Route::prefix('uploads')->group(function () {
    // Start an upload
    Route::post('/servers/{server}/upload', [UploadController::class, 'uploadFile'])
        ->name('server.upload');

    // Upload multiple files
    Route::post('/servers/{server}/upload-multiple', [UploadController::class, 'uploadMultipleFiles'])
        ->name('server.upload.multiple');

    // Get progress by progress key
    Route::get('/progress/{key}', [UploadController::class, 'getUploadProgress'])
        ->name('upload.progress');

    // Get progress by ID
    Route::get('/progress/id/{id}', [UploadController::class, 'getUploadProgressById'])
        ->name('upload.progress.by.id');

    // List all uploads for a server
    Route::get('/servers/{server}', [UploadController::class, 'listServerUploads'])
        ->name('server.uploads.list');

    // Cancel an upload
    Route::post('/cancel/{key}', [UploadController::class, 'cancelUpload'])
        ->name('upload.cancel');

    // Delete an upload record
    Route::delete('/{key}', [UploadController::class, 'deleteUpload'])
        ->name('upload.delete');

    // Retry a failed upload
    Route::post('/retry/{key}', [UploadController::class, 'retryUpload'])
        ->name('upload.retry');

    // Get upload statistics for a server
    Route::get('/servers/{server}/stats', [UploadController::class, 'getUploadStats'])
        ->name('server.upload.stats');

    // Clean up completed uploads older than X days
    Route::post('/cleanup', [UploadController::class, 'cleanupOldUploads'])
        ->name('upload.cleanup');

    // Browse remote directory structure
    Route::get('/servers/{server}/browse-remote', [UploadController::class, 'browseRemoteDirectory'])
        ->name('server.browse.remote');

    // Validate remote path exists
    Route::post('/servers/{server}/validate-path', [UploadController::class, 'validateRemotePath'])
        ->name('server.validate.remote.path');
});

Route::get('/delete-file', function () {
    if (Storage::exists('001b298425d76eb3ffb3898db7d4d9e3.json')) {
        return 'yes';
    }
    return 'no';
});

Route::get('/test-file', function () {
    $path = storage_path('app/001b298425d76eb3ffb3898db7d4d9e3.json');
    return file_exists($path) ? 'found via native PHP' : 'not found';
});


Route::get('db-credentials', function (SSHService $sshService) {
    // Load the full .env file contents
    $command = 'cd /var/www/html/dtr/ && type .env || cat .env';

    $output = $sshService->executeCommand(Server::find(1), $command, true);
// Normalize line endings
    $lines = preg_split('/\r\n|\n|\r/', trim($output));

    $wantedKeys = ['DB_PORT', 'DB_DATABASE', 'DB_USERNAME','DB_PASSWORD','DB_CONNECTION'];
    $envVars = [];

    foreach ($lines as $line) {
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if (in_array($key, $wantedKeys)) {
                $envVars[$key] = trim($value);
            }
        }
    }

    dd($envVars);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
