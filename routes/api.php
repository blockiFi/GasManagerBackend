<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\DispenserController;
use App\Http\Controllers\OperationCostController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\BusinessUserController;
use App\Http\Controllers\ImageAnalysisController;
use App\Http\Controllers\WhatsAppController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// WhatsApp Webhook Routes (no authentication required)
Route::get('/whatsapp/webhook', [WhatsAppController::class, 'verifyWebhook']);
Route::post('/whatsapp/webhook', [WhatsAppController::class, 'handleWebhook']);
Route::post('/whatsapp/verify-otp', [WhatsAppController::class, 'verifyOTP'])
    ->middleware('throttle:login');


Route::get('/AuthError', [AuthController::class, 'AuthError']);
Route::post('/register', [AuthController::class, 'Register']);
Route::post('/login', [AuthController::class, 'Login'])->middleware('throttle:login');


Route::middleware( ['auth:sanctum' ])->group(function () {
    Route::post('/analyze-image', [ImageAnalysisController::class, 'upload'])
        ->middleware('throttle:analyze-image');
    Route::post('/register_business', [BusinessController::class, 'createBusiness']);
    Route::middleware( ['BusinessOwner'])->group(function () {
        
        Route::post('/update_business', [BusinessController::class, 'updateBusiness']);
       
        Route::post('/business/add_location', [LocationController::class, 'addBusinessLocation']);
        Route::post('/business/update_location', [LocationController::class, 'updateBusinessLocation']);
        Route::post('/business/add_dispenser', [DispenserController::class, 'AddDispenser']);
        Route::post('/business/update_dispenser', [DispenserController::class, 'UpdateDispenser']);
        Route::post('/business/update_dispenser/active', [DispenserController::class, 'setDispenserActive']);
        Route::post('/business/update_dispenser/setting', [DispenserController::class, 'updateDispenserSaleSettings']);
        Route::post('/business/location/set_price', [PriceController::class, 'setLocationPrice']);
        Route::post('/business/location/change_manager', [LocationController::class, 'changeManager']);
        Route::post('/business/supplier/add_business_supplier', [SupplierController::class, 'addSupplier']);
        Route::post('/business/supplier/update_business_supplier', [SupplierController::class, 'updateSupplier']);
        
        Route::post('/business/supply/add_business_supply', [SupplyController::class, 'addSupply']);

        Route::post('/business/sales/confirm_sales', [SalesController::class, 'confirmSales']);
        Route::post('/business/supplier/get_business_suppliers', [SupplierController::class, 'getBisinessSupliers']);

        Route::post('/business/users/get_business_users', [BusinessUserController::class, 'getBusinessUsers']);
        Route::post('/business/users/reset_password', [BusinessUserController::class, 'ResetPassword']);
        Route::post('/business/users/add_employee', [BusinessUserController::class, 'AddEmployee']);
        
    });
    Route::middleware( ['businessManager'])->group(function () {
        
        Route::post('/get_business/operational_cost/{range?}', [OperationCostController::class, 'getOperationCost']);
        Route::post('/get_business/operational_cost_details', [OperationCostController::class, 'getAllLocationCostSummery']);
        Route::post('/business/location/add_operational_cost', [OperationCostController::class, 'addOperationCost']);
        Route::post('/get_business/location/current_price', [PriceController::class, 'getLocationCurrentPrice']);
        Route::post('/get_business/location/price_history', [PriceController::class, 'getLocationPriceHistory']);
        Route::post('/get_business/location/sales/{dispenser?}', [SalesController::class, 'getLocationSales']);

        Route::post('/business/location/add_sales', [SalesController::class, 'addSales']);
       

        Route::post('/business/supply/get_business_supplies', [SupplyController::class, 'getSupplies']);
        Route::post('/business/supply/get_supply_details', [SupplyController::class, 'getSupplyDetails']);
        Route::post('/business/supply/confirm_business_supply', [SupplyController::class, 'confirmSupply']);
        Route::post('/business/supply/close_business_supply', [SupplyController::class, 'closeSupply']);
        Route::post('/business/supply/transfer_business_supply', [SupplyController::class, 'transferSupply']);

        Route::post('/business/sales/upload_reciept', [SalesController::class, 'uploadReciept']);
        Route::post('/business/sales/get_reciept', [SalesController::class, 'getSalesReceipts']);
        Route::post('/business/sales/edit_sale_date', [SalesController::class, 'editSaleDate']);
        Route::post('/business/sales/reverse_latest_sale', [SalesController::class, 'reverseLatestSale']);
        
        Route::post('/get_business/location/dispenser', [DispenserController::class, 'getLocationDispensers']);
        
        
        
    });   

    Route::post('/get_business/locations/{withDispenser?}', [LocationController::class, 'getBusinessLocations']);
    Route::post('/business/location/{with_price}', [LocationController::class, 'getBusinessLocationsWithPrice']);
    Route::get('/get_business', [BusinessController::class, 'getUserBusiness']);
    Route::get('/get_business/sales', [BusinessController::class, 'getUserBusinessWithSales']);
    
    Route::get('/get_business/all_dispenser', [DispenserController::class, 'getAllBusinessDispensers']);
    Route::get('/get_business/get_sales_profit/{supply_id}', [SalesController::class, 'getSalesProfit']);
    Route::post('/get_business/get_sales_between', [SalesController::class, 'getSalesBetween']);
    Route::post('/get_business/get_sales_data', [SalesController::class, 'getSalesBreakdown']);
    Route::post('/get_business/get_month_sales_data', [SalesController::class, 'getMonthSales']);
    Route::post('/get_business/sales_by_group' , [SalesController::class , 'getSalesGroupedByWeeks']);

    Route::get('/get_business/settings/{withBusiness?}', [SettingsController::class, 'getGeneralSettings']);

    Route::post('/business/settings/add_setting', [SettingsController::class, 'addSeting']);
    Route::post('/business/settings/initialize_business_settings', [SettingsController::class, 'initializeBusinessSettings']);
    Route::post('/business/settings/set_business_setting', [SettingsController::class, 'setBusinessSetting']);
    Route::post('/business/settings/get_business_setting', [SettingsController::class, 'getBusinessSetings']);



    Route::post('/get_business/settings/get_settings', [SettingsController::class, 'getUserBusinessSetings']);
    Route::post('/get_business/settings/update_settings', [SettingsController::class, 'UpdateUserBusinessSetings']);


   
});