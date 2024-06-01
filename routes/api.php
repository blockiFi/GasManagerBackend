<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\HTTP\Controllers\AuthController;
use App\HTTP\Controllers\BusinessController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::post('/register', [AuthController::class, 'Register']);
Route::post('/login', [AuthController::class, 'Login']);


Route::middleware( ['auth:api'])->group(function () {

Route::post('/register_business', [BusinessController::class, 'createBusiness']);
Route::post('/update_business', [BusinessController::class, 'updateBusiness']);
Route::get('/get_business', [BusinessController::class, 'getUserBusiness']);
Route::get('/get_business/sales', [BusinessController::class, 'getUserBusinessWithSales']);

});