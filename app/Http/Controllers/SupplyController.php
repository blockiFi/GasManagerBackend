<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\Models\Business;
use App\Models\Dispenser;
use App\Models\Supply;
use Carbon\Carbon;
class SupplyController extends Controller
{
    //
    public function getSupplies(Request $request , $location = null , $dispenser = null){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id"
      ]);
    
      if ($validator->fails()) {
    
           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      } 
      if($location){
        if($dispenser){
        $supplies =  Supply::where([['business_id' , '=' , $request->business_id] , ['location_id' , '=' , $location] , ['dispenser_id' , '=' , $dispenser]])->with(['Dispenser' , 'Location' , 'Supplier' , 'Reciever'] )->get();
        
        }
        else{
        $supplies =  Supply::where([['business_id' , '=' , $request->business_id] , ['location_id' , '=' , $location]])->with(['Dispenser' , 'Location' , 'Supplier' , 'Reciever'] )->get();

        }
      }
      else{
        $supplies =  Supply::where('business_id' , '=' , $request->business_id)->with(['Dispenser' , 'Location' , 'Supplier' , 'Reciever'] )->get();


      }
      $response['data'] = $supplies;
      return response()->json($response ,200);
    }

    public function addSupply(Request $request){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id",
            "location_id" => "required|exists:locations,id",
            "dispenser_id" => "required|exists:dispensers,id",
            "quantity" => "required",
            "amount" => "required",
            "supplier_id" => "required",
            
      ]);
    
      if ($validator->fails()) {
    
           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      } 

      $supply =  new Supply;
      $supply->business_id = $request->business_id;
      $supply->location_id = $request->location_id;
      $supply->dispenser_id = $request->dispenser_id;
      $supply->quantity = $request->quantity;
      $supply->available_quantity = $request->quantity;
      $supply->sold = 0;
      $supply->profit = 0;
      $supply->amount = $request->amount;
      $supply->supplier_id = $request->supplier_id;
      $supply->excess_kg = 0;
      $supply->purchased_at = Carbon::now();
      $supply->save();
      $response['message'] = "Supply Added Successfully!!!";
      return response()->json($response ,200);
    }
    public function confirmSupply(Request $request){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id",
            "supply_id" => "required|exists:supplies,id",
            "note" => 'string',

            
      ]);
    
      if ($validator->fails()) {
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      } 

      $supply = Supply::find($request->supply_id);
      if($request->business_id != $supply->business_id){
        $response['errors'] = ['This supply does not belong to this business'];
        return response()->json($response ,400);
      }
      $supply->recieved_by = Auth::user()->id;
      $supply->note = $request->note;
      $supply->delivered_at =Carbon::now();
      $supply->supplied = true;
      $supply->save();

      $dispenser  = Dispenser::find($supply->dispenser_id);
      $dispenser->current_level =  (float)$dispenser->current_level + (float)$supply->quantity;
      $dispenser->save();
      $response['message'] = "Supply Updated Successfully!!!";
      return response()->json($response ,200);

    }
}
