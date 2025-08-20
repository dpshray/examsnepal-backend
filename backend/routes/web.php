<?php

use App\Models\PromoCode;
use App\Services\ConnectIPSService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Route::get('cips', function(){});

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

Route::get('data-inserter', function(){
    $data = [
        [
            'code' => 'DWORK2025',
            'discount_percent' => 5,
            'detail' => 'DWORK FESTIVAL'
        ],
        [
            'code' => 'KATHMANDUWEAR2081',
            'discount_percent' => 2,
            'detail' => 'KATHMANDU WEAR 2081'
        ],
        [
            'code' => 'DASHAIN-2081',
            'discount_percent' => 3,
            'detail' => 'DASHAIN 2081'
        ],
    ];
    foreach ($data as $item) {
        $rows = PromoCode::firstWhere('code', $item['code']);
        if (empty($rows)) {
            DB::table('promo_codes')->insert($item);
        }
    }
    echo 'OK';
});