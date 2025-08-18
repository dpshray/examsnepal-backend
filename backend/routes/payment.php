<?php

use App\Http\Controllers\Api\Payment\ConnectIPSController;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth:api'])
    ->controller(ConnectIPSController::class)
    ->prefix('connectips')
    ->group(function(){
        Route::get('init-transaction/{subscription_type}', 'beginTransaction');
        Route::get('transaction-successfull/{transaction_id}', 'successPayment');
        // Route::post('store-transaction', 'transactionStore');
})->middleware('verified');