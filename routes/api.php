<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\HTTP\Controllers\AuthController;
use App\HTTP\Controllers\BusinessController;
use App\HTTP\Controllers\LocationController;
use App\HTTP\Controllers\DispenserController;
use App\HTTP\Controllers\OperationCostController;
use App\HTTP\Controllers\PriceController;
use App\HTTP\Controllers\SalesController;
use App\HTTP\Controllers\SettingsController;
use App\HTTP\Controllers\SupplierController;
use App\HTTP\Controllers\SupplyController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');
Route::get('/AuthError', [AuthController::class, 'AuthError']);
Route::post('/register', [AuthController::class, 'Register']);
Route::post('/login', [AuthController::class, 'Login']);


Route::middleware( ['auth:api' ])->group(function () {

    Route::middleware( ['BusinessOwner'])->group(function () {
        Route::post('/register_business', [BusinessController::class, 'createBusiness']);
        Route::post('/update_business', [BusinessController::class, 'updateBusiness']);
        Route::post('/get_business/locations', [LocationController::class, 'getBusinessLocations']);
        Route::post('/business/add_location', [LocationController::class, 'addBusinessLocation']);
        Route::post('/business/update_location', [LocationController::class, 'updateBusinessLocation']);
        Route::post('/business/add_dispenser', [DispenserController::class, 'AddDispenser']);
        Route::post('/business/update_dispenser', [DispenserController::class, 'UpdateDispenser']);
        Route::post('/business/location/set_price', [PriceController::class, 'setLocationPrice']);
        Route::post('/business/supplier/add_business_supplier', [SupplierController::class, 'addSupplier']);
        Route::post('/business/supplier/update_business_supplier', [SupplierController::class, 'updateSupplier']);
        
        Route::post('/business/supply/add_business_supply', [SupplyController::class, 'addSupply']);

        Route::post('/business/sales/confirm_sales', [SalesController::class, 'confirmSales']);
        
        
    });
    Route::middleware( ['businessManager'])->group(function () {
        Route::post('/get_business/operational_cost/{range?}', [OperationCostController::class, 'getOperationCost']);
        Route::post('/business/location/add_operational_cost', [OperationCostController::class, 'addOperationCost']);
        Route::post('/get_business/location/current_price', [PriceController::class, 'getLocationCurrentPrice']);
        Route::post('/get_business/location/price_history', [PriceController::class, 'getLocationPriceHistory']);
        Route::post('/get_business/location/sales/{dispenser?}', [SalesController::class, 'getLocationSales']);

        Route::post('/business/location/add_sales', [SalesController::class, 'addSales']);
        Route::post('/business/supplier/get_business_suppliers', [SupplierController::class, 'getBisinessSupliers']);

        Route::post('/business/supply/get_business_supplies', [SupplyController::class, 'getSupplies']);
        Route::post('/business/supply/confirm_business_supply', [SupplyController::class, 'confirmSupply']);

        Route::post('/business/sales/upload_reciept', [SalesController::class, 'uploadReciept']);
        Route::post('/business/sales/get_reciept', [SalesController::class, 'getSalesReceipts']);
        
        
        
        
    });   
     
    Route::get('/get_business', [BusinessController::class, 'getUserBusiness']);
    Route::get('/get_business/sales', [BusinessController::class, 'getUserBusinessWithSales']);
    Route::get('/get_business/location/dispenser/{location_id}', [DispenserController::class, 'getLocationDispensers']);
    Route::get('/get_business/all_dispenser', [DispenserController::class, 'getAllBusinessDispensers']);

    Route::get('/get_business/settings/{withBusiness?}', [SettingsController::class, 'getGeneralSettings']);

    Route::post('/business/settings/add_setting', [SettingsController::class, 'addSeting']);
    Route::post('/business/settings/initialize_business_settings', [SettingsController::class, 'initializeBusinessSettings']);
    Route::post('/business/settings/set_business_setting', [SettingsController::class, 'setBusinessSetting']);
    Route::post('/business/settings/get_business_setting', [SettingsController::class, 'getBusinessSetings']);
    
});