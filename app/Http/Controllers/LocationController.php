<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\Models\Business;
use App\Models\Location;
use App\Models\Business_User;


class LocationController extends Controller 
{






    public function getBusinessLocations(Request $request){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id"
      ]);

      if ($validator->fails()) {

           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }
         $response['data'] = $this->getLocations($request->business_id);
           return response()->json($response ,200); 
    }

    public function addBusinessLocation(Request $request){
        $validator = Validator::make($request->all(), [
            'business_id' => 'required|exists:businesses,id',
            "name" =>  "required",
            'address' => 'required',
            'user_id' => 'required|exists:users,id'
      ]);

      if ($validator->fails()) {

           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }

      
      $isWithTheBusiness = Business_User::where([['business_id' , '=' , $request->business_id],['user_id' , '=' , $request->user_id]])->first();
      if($isWithTheBusiness){
        $location = new Location;
        $location->name = $request->name;
        $location->address = $request->address;
        $location->business_id = $request->business_id;
        $location->manager_id = $request->user_id;

        $location->save();
        
        $response['data'] = $this->getLocations($request->business_id);
        $response['message'] = "Location Added Successfully";
        return response()->json($response ,200); 
      }
      else{
        $response['code'] = 400;
        $response['errors'] =["selected user not with the Business"];
        return response()->json($response ,400);
      }
      
     
    }
    public function updateBusinessLocation(Request $request){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id",
            "id" => "required|exists:locations",
            "name" =>  "required",
            'address' => 'required',
      ]);

      if ($validator->fails()) {

           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }

      $location = Location::find($request->id);
      $location->name = $request->name;
      $location->address = $request->address;
      $location->save();
      $response['data'] = $location;
        $response['message'] = "Location Updates Successfully";
        return response()->json($response ,200); 

    }
    public function getLocations($business_id){
         $business = business::where('id' , $business_id)->with(['Locations' , 'Locations.Manager'])->first();
      
       return $business;
    }

}
