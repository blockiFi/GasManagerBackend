<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Auth;
use Validator;
use App\Models\Business;
use App\Models\Location;
use App\Models\Sale;
use App\Models\Price;
use Carbon\Carbon;
use App\Models\Sale_Reciept;
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

    if((float)$request->closing_sales >= (float)$prevSales->closing_sales){
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

public function  uploadReciept(Request $request){
    $validator = Validator::make($request->all(), [
        "business_id" => "required|exists:businesses,id",
        "sales_id" => "required|exists:sales,id",
        "location_id" => "required|exists:locations,id",
        'files.*' => 'required|file|mimes:jpeg,png,jpg'
  ]);

  if ($validator->fails()) {

       
        $response['code'] = 400;
        $response['errors'] = $validator->messages()->all();
        return response()->json($response ,400);
  } 
  

        $sales = Sale::find($request->sales_id);
        $filePaths = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('receipts', 'public'); // Store file in the "public/uploads" directory
                $filePaths[] = $path;
                $reciept =  new Sale_Reciept;
                $reciept->sales_id = $request->sales_id;
                $reciept->image_path = $path;
                $reciept->save();
            }
            $sales->status = "confirming";
            $sales->save();
            $response['message'] = 'Sales Reciepts Added Successfully!!!';
            $response['file_paths'] = $filePaths;
            return response()->json($response ,200);
        }
        else{
                $response['code'] = 400;
                $response['errors'] = ["Receipt image not available"];
                return response()->json($response ,400);
            }
    
}

public function getSalesReceipts(Request $request){
    $validator = Validator::make($request->all(), [
        "business_id" => "required|exists:businesses,id",
        "sales_id" => "required|exists:sales,id",
  ]);

  if ($validator->fails()) {

       
        $response['code'] = 400;
        $response['errors'] = $validator->messages()->all();
        return response()->json($response ,400);
  }  

  $receipt = Sale_Reciept::where("sales_id" , "=" , $request->sales_id)->get();
            $response['data'] = $receipt;
            return response()->json($response ,200);
}

public function confirmSales(Request $request){
        $validator = Validator::make($request->all(), [
                "business_id" => "required|exists:businesses,id",
                "location_id" => "required|exists:locations,id",
                "sales_id" => "required|exists:sales,id",
        ]);

        if ($validator->fails()) {

            
                $response['code'] = 400;
                $response['errors'] = $validator->messages()->all();
                return response()->json($response ,400);
        } 
  
        $business = Business::find($request->business_id);
        if(Auth::user()->id != $business->owner_id){
            $response['code'] = 400;
                $response['errors'] = ["Only Business Owner can Confirm Payment"];
                return response()->json($response ,400);
        }

        $sales = Sale::find($request->sales_id);
        $sales->status = "confirmed";
        $sales->save();
        $response['code'] = 200;
        $response['message'] = "Sale Payment Confirmed Successfully!!!";
        return response()->json($response ,200);
}
}
