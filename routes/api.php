<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Resources\UserCustomerResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'admin'])
    ->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
        Route::get('/users', function (Request $request) {
            // $users = User::with('customer')->where('id' ,$request->user->id )->get() ;
            // return UserCustomerResource::collection($users) ;
            $user = $request->user()->id ;
            $u = User::with('customer')->where('id', $user)->get() ;
            return UserCustomerResource::collection($user) ;
        });
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::apiResource('/products', ProductController::class);
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'view']);
    });
