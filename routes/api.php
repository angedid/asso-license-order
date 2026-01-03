<?php

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::post('tenant-provisions', [ApiController::class, 'provisionTenant'])->middleware('rabbitmq.client');

Route::post('access-tokens', [ApiController::class, 'saveAccessToken'])->middleware('rabbitmq.client');

Route::post('logout', [ApiController::class, 'logout'])->middleware('rabbitmq.client');

Route::post('/orders', [ApiController::class, 'createOrder'])->middleware('iam.auth.api');

Route::put('/orders/{order_id}/placed-order', [ApiController::class, 'updateOrderOrderPlaced'])->middleware('iam.auth.api');

Route::put('/orders/{order_id}/paid-order', [ApiController::class, 'updateOrderOrderPaid'])->middleware('rabbitmq.client');

Route::put('/orders/{order_id}/cancelled-order', [ApiController::class, 'updateOrderOrderCancelled'])->middleware('iam.auth.api');



