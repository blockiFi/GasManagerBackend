<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\HTTP\Controllers\AuthController;
use App\HTTP\Controllers\BusinessController;
use App\HTTP\Controllers\LocationController;
use App\HTTP\Controllers\DispenserController;

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

Route::get('/get_business/locations', [LocationController::class, 'getBusinessLocations']);
Route::post('/business/add_location', [LocationController::class, 'addBusinessLocation']);
Route::post('/business/update_location', [LocationController::class, 'updateBusinessLocation']);
Route::post('/business/add_dispenser', [DispenserController::class, 'AddDispenser']);
Route::get('/get_business/location/dispenser/{location_id}', [DispenserController::class, 'getLocationDispensers']);
Route::get('/get_business/all_dispenser', [DispenserController::class, 'getAllBusinessDispensers']);



});