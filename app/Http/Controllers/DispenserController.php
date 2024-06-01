<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business;
use App\Models\Location;
use App\Models\Dispenser;
use Validator;
use Auth;
class DispenserController extends Controller
{
    //
    public function AddDispenser(Request $request){
        $validator = Validator::make($request->all(), [
            "location_id" => "required|exists:locations,id",
            "name" =>  "required",
            'capacity' => 'required',
      ]);

      if ($validator->fails()) {

           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }
      $business = Auth::user()->Business()->with('Business')->first()['Business'];
      $location = Location::find($request->location_id);
      if($location->business_id == $business->id){
        $dispenser = new Dispenser;
        $dispenser->name = $request->name;
        $dispenser->business_id = $business->id;
        $dispenser->location_id = $request->location_id;
        $dispenser->capacity = $request->capacity;
        $dispenser->current_level = 0;
        $dispenser->save();
        $response['data'] = $this->getDispensers($request->location_id);
        $response['message'] = "Dispenser Added Successfully";
        return response()->json($response ,200); 
      }else{
            $response['code'] = 400;
            $response['errors'] = ['location selected not under this business'];
            return response()->json($response ,400);
      }
    }
    public function getLocationDispensers($location_id){
        $response['data'] = $this->getDispensers($location_id);
        return response()->json($response ,200); 
    }
    public function getDispensers($location_id){
        return Dispenser::where('location_id' , '=' , $location_id)->with(['Sales' ,'Sales.Price'])->get();
    }

    public function getAllBusinessDispensers(){
        $business = Auth::user()->Business()->with('Business')->first()['Business'];
        $dispensers = Dispenser::where('business_id' , '=' , $business->id)->with(['Sales' ,'Sales.Price'])->get();
        $response['data'] = $dispensers;
        return response()->json($response ,200); 
    }
}
