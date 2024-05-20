<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'admin'])
    ->group(function(){
        Route::get('/user', function(Request $request){ return $request->user(); });
        Route::post('/logout', [AuthController::class,'logout']);
        Route::apiResource('/product', ProductController::class);
});
