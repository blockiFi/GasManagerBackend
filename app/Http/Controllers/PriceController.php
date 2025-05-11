<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\Models\Business;
use App\Models\Location;
use App\Models\Price;
class PriceController extends Controller
{
    //
    public function getLocationCurrentPrice(Request $request){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id",
            "location_id" => "required|exists:locations,id"
      ]);

      if ($validator->fails()) {

           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }

      $price = Price::where([['business_id' ,'=' , $request->business_id] , ['location_id' , '=' , $request->location_id] , ['active' , '=' , 'true']])->first();
      if($price){
        $response['price'] = $price;
        return response()->json($response ,200); 
      }
      $response['error'] = ['Price not set for this location'];
      return response()->json($response ,400); 
    }
    public function getLocationPriceHistory(Request $request){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id",
            "location_id" => "required|exists:locations,id"
      ]);

      if ($validator->fails()) {

           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }
      $prices = Price::where([['business_id' ,'=' , $request->business_id] , ['location_id' , '=' , $request->location_id] ])->get();
      $response['data'] = $prices;
      return response()->json($response ,200); 
    }
    public function setLocationPrice(Request $request){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id",
            "location_id" => "required|exists:locations,id",
            "price" => "required|numeric"
      ]);

      if ($validator->fails()) {

           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      } 
      $prices = Price::where([['business_id' ,'=' , $request->business_id] , ['location_id' , '=' , $request->location_id] , ['active' , '=' , 'true']])->get();
      foreach($prices as $price){
        $price->active = 'false';
        $price->save();
      }
      
      $price = new Price;
      $price->business_id = $request->business_id;
      $price->location_id = $request->location_id;
      $price->price = $request->price;
      $price->active = 'true';
      $price->set_by = Auth::user()->id;
      $price->save();
      $response['message'] ="Price Added Successfully!!!";
      return response()->json($response ,200); 
    }
}
