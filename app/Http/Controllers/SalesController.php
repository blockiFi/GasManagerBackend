<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\Models\Business;
use App\Models\Location;
use App\Models\Sale;
use App\Models\Price;
use Carbon\Carbon;
class SalesController extends Controller
{
    //
    // get loaction sales
public function getLocationSales(Request $request , $dispenser = null){
    $validator = Validator::make($request->all(), [
        "business_id" => "required|exists:businesses,id",
        "location_id" => "required|exists:locations,id"
  ]);

  if ($validator->fails()) {

       
        $response['code'] = 400;
        $response['errors'] = $validator->messages()->all();
        return response()->json($response ,400);
  }
if($dispenser){
    $sales = Sale::where([['business_id' , '=' , $request->business_id] , ['location_id' , '=' , $request->location_id ] , ['dispenser_id' , '=' ,$dispenser ]])->with(['Price' , 'Dispenser'] )->get();

}else{
    $sales = Sale::where([['business_id' , '=' , $request->business_id] , ['location_id' , '=' , $request->location_id]])->with(['Price' , 'Dispenser'] )->get();


}
$response['data'] =$sales;
return response()->json($response ,200);
}

public function addSales(Request $request){
    $validator = Validator::make($request->all(), [
        "business_id" => "required|exists:businesses,id",
        "location_id" => "required|exists:locations,id",
        "dispenser_id" => "required|exists:dispensers,id",
        "opening_sales" => "required|numeric",
        "closing_sales" => "required|numeric",
        "opening_kg" => "required|numeric",
        "closing_kg" => "required|numeric",
        "sales_date" => "required|date"


  ]);

  if ($validator->fails()) {

       
        $response['code'] = 400;
        $response['errors'] = $validator->messages()->all();
        return response()->json($response ,400);
  } 

   $prevSales = Sale::where([['business_id' ,'=' , $request->business_id ] ,['location_id' ,'=' , $request->location_id ] ,['dispenser_id' ,'=' , $request->dispenser_id ]])->latest()->first();
  $price = Price::where([['business_id' ,'=' , $request->business_id] , ['location_id' , '=' , $request->location_id] , ['active' , '=' , 'true']])->first();
  if($prevSales){
    $sales = new Sale;
    $sales->business_id = $request->business_id;
    $sales->location_id = $request->location_id;
    $sales->dispenser_id = $request->dispenser_id;
    $sales->opening_sales = $prevSales->closing_sales;
    $sales->closing_sales = $request->closing_sales;
    $sales->opening_kg = $prevSales->closing_kg;
    $sales->closing_kg = $request->closing_kg;

    if((float)$request->closing_sales > (float)$prevSales->closing_sales){
        $sales->amount = (float)$request->closing_sales - (float)$prevSales->closing_sales;
    }else{
        $sales->amount = (1000000 + (float)$request->closing_sales) - (float)$prevSales->closing_sales;
    }
    
    $sales->kg_quantity = (float)$request->closing_kg - (float)$prevSales->closing_kg;
    $sales->sales_date = Carbon::parse($request->sales_date);
    $sales->uploaded_by = Auth::user()->id;
    $sales->price_id  = $price->id;
    $sales->save();
  }else{

    $sales = new Sale;
    $sales->business_id = $request->business_id;
    $sales->location_id = $request->location_id;
    $sales->dispenser_id = $request->dispenser_id;
    $sales->opening_sales = $request->opening_sales;
    $sales->closing_sales = $request->closing_sales;
    $sales->opening_kg = $request->opening_kg;
    $sales->closing_kg = $request->closing_kg;

    if((float)$request->closing_sales > (float)$request->opening_sales){
        $sales->amount = (float)$request->closing_sales - (float)$request->opening_sales;
    }else{
        $sales->amount = (10000000 + (float)$request->closing_sales) - (float)$request->opening_sales;
    }
    
    $sales->kg_quantity = (float)$request->closing_kg - (float)$request->opening_kg;;
    $sales->sales_date = Carbon::parse($request->sales_date);
    $sales->uploaded_by = Auth::user()->id;
    $sales->price_id  = $price->id;
    $sales->save();
  
  }
  $response['message'] = "Sales Added Successfully!!!";
return response()->json($response ,200);
}
}
