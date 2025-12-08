<?php

use App\Models\PromoCode;
use App\Services\ConnectIPSService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

Route::get('cips', function(){});

Route::get('/download-logs', function(){
    $logPath = storage_path('logs');
    if (!File::exists($logPath)) {
        return abort(404, "Log directory not found.");
    }
    $zipFile = storage_path('logs.zip');
    $zip = new ZipArchive;
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $files = File::files($logPath);
        foreach ($files as $file) {
            $zip->addFile($file->getRealPath(), $file->getFilename());
        }
        $zip->close();
    }
    return response()->download($zipFile)->deleteFileAfterSend(true);
});

Route::get('/', function () {
    return view('welcome');
});

Route::get('/doc', function () {
    Artisan::call('l5-swagger:generate');
    return redirect('/api/documentation');
});

Route::get('/doc-2', function () {
    Artisan::call('l5:generate');
    return redirect('/api/documentation');
});