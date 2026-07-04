<?php

use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ApiResponse::success([
        'service' => config('app.name'),
        'status' => 'ok',
    ], 'AMRV API backend is running');
});
