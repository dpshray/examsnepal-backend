<?php

use App\Http\Controllers\Api\Payment\ConnectIPSController;
use App\Http\Controllers\Api\Payment\Esewa\EsewaController;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth:api'])
    ->controller(ConnectIPSController::class)
    ->prefix('connectips')
    ->group(function () {
        Route::post('init-transaction', 'beginTransaction');
        Route::get('transaction-successfull/{transaction_id}', 'successPayment');
        // Route::post('store-transaction', 'transactionStore');
    })->middleware('verified');

Route::controller(EsewaController::class)
    ->prefix('esewa')
    ->group(function () {
        // Requires the logged-in student — stays behind auth
        Route::middleware(['auth:api', 'verified'])
            ->post('init-transaction', 'beginTransaction');

        // eSewa itself hits these via browser GET redirect — must stay public
        Route::get('transaction-successfull', 'successPayment')
            ->name('api.esewa.success');
        Route::get('transaction-failed', 'failurePayment')
            ->name('api.esewa.failure');
    });
