<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});
// Route::get('/documentation', function () {
//     return response()->file(public_path('storage.api-docs.api.json')); // Serve the swagger.json file
// })->withoutMiddleware('auth:api');

Route::get('/doc', function () {
    Artisan::call('l5-swagger:generate');
    return redirect('/api/documentation');
});

Route::get('dummy-data-inserter', function(){
    $temp = [];
    DB::table('exam_types')->get()->each(function($item, $key) use(&$temp){
        foreach ([1 => 100, 3 => 300, 6 => 500, 12 => 1000] as $month => $price) {
            $temp[] = [
                'exam_type_id' => $item->id,
                'duration' => $month,
                'price' => $price + $item->id
            ];
        }
    });
    DB::table('subscription_types')->insert($temp);
    echo 'INSERTED';
});
