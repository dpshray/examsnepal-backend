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
    Artisan::call('l5-swagger:generate'); // Run the Swagger generate command
    return redirect('/api/documentation'); // Redirect to Swagger UI
});