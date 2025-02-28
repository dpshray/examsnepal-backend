<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
// Route::get('/documentation', function () {
//     return response()->file(public_path('storage.api-docs.api.json')); // Serve the swagger.json file
// })->withoutMiddleware('auth:api');