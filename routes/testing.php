<?php

use Illuminate\Support\Facades\Route;

// Admin only: logs hold error traces and payment data, and institute accounts are self-registered.
Route::middleware(['auth:users', 'role:admin'])->get('/download-logs', function () {
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