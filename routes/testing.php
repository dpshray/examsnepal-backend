<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:users'])->get('/download-logs', function () {
    if (auth()->user()->role->name == 'teacher') {
        return 'Not Authorized';
    }
    $logPath = storage_path('logs');
    $zipFile = storage_path('app/logs.zip');
    // Delete existing zip if exists
    if (file_exists($zipFile)) {
        unlink($zipFile);
    }
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
        $files = glob($logPath . '/*.log');

        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();
    } else {
        return response()->json(['error' => 'Cannot create zip file.'], 500);
    }
    return response()->download($zipFile)->deleteFileAfterSend(true);
});