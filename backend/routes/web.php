<?php

use App\Services\ConnectIPSService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Route::get('cips', function(){
    app(ConnectIPSService::class)->initiateTransaction([]);
});

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


Route::get('inserter', function(){
    $name = \App\Enums\RoleEnum::PARTICIPANT;
    $is_null = DB::table('roles')->where('name', $name)->first();
    if (empty($is_null)) {
        DB::table('roles')->insert([
            ['name' => \App\Enums\RoleEnum::PARTICIPANT]
        ]);
        echo 'INSERTED';
    } else {
        echo 'ALREADY INSERTED';
    }
});
// Route::get('dummy-data-inserter', function(){
//     $temp = [];
//     DB::select('ALTER TABLE subscription_types AUTO_INCREMENT = 1');
//     DB::table('exam_types')->get()->each(function($item, $key) use(&$temp){
//         foreach ([1 => 100, 3 => 300, 6 => 500, 12 => 1000] as $month => $price) {
//             $temp[] = [
//                 'exam_type_id' => $item->id,
//                 'duration' => $month,
//                 'price' => $price + $item->id
//             ];
//         }
//     });
//     DB::table('subscription_types')->insert($temp);
//     echo 'INSERTED';
// });
