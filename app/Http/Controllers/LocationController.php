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
    //
    public function getBusinessLocations(){

        
         $response['data'] = $this->getLocations();
           return response()->json($response ,200); 
    }

    public function addBusinessLocation(Request $request){
        $validator = Validator::make($request->all(), [
            "name" =>  "required",
            'address' => 'required',
            'user_id' => 'required|exists:users,id'
      ]);

      if ($validator->fails()) {

           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }

      $business = Auth::user()->Business()->with('Business')->first()['Business'];
      $isWithTheBusiness = Business_User::where([['business_id' , '=' , $business->id],['user_id' , '=' , $request->user_id]])->first();
      if($isWithTheBusiness){
        $location = new Location;
        $location->name = $request->name;
        $location->address = $request->address;
        $location->business_id = $business->id;
        $location->manager_id = $request->user_id;

        $location->save();
        
        $response['data'] = $this->getLocations();
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
    public function getLocations(){
        $businessData =  Auth::user()->Business()->with(['Business' , 'Business.Locations' , 'Business.Locations.Manager'])->first()['Business'];
        
        if($businessData && $businessData->owner_id == Auth::user()->id){
           
        }
        else{
            $location = Location::where('manager_id' , '=' ,Auth::user()->id )->get();
            $businessData['locations'] = $location ;
        }
       return $businessData;
    }

}
