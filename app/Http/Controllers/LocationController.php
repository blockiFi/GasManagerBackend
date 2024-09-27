<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\Models\Business;
use App\Models\Location;
use App\Models\Business_User;
use App\Models\Price;


class LocationController extends Controller 
{






    public function getBusinessLocations(Request $request , $withDispenser = '' ){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id"
      ]);

      if ($validator->fails()) {

           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }
         $business = Business::find($request->business_id);

         if($withDispenser == 'price'){
          $locations = Location::where('business_id' ,'=' , $request->business_id)->get();
          foreach($locations as $key => $location){
            // $location 'location_id', '=' , $location->id
            $price = Price::where([['location_id', '=' , $location->id ] , ['active' , '=' , 'true']])->first();
            if($price){
              $locations[$key]['active_price'] = $price->price;
            }else{
              $locations[$key]['active_price'] = 0;
            }
            
          }
          $business['locations'] = $locations;
          $response['data']  = $business;
        
          
           return response()->json($response ,200); 
         }
         if(Auth::user()->id == $business->owner_id){
          $business['locations'] =  Location::where('business_id' , '=' ,$business->id)->with(['Manager' , 'Dispensers'])->get();
          $response['data'] = $business;
         }
        else{
          $business['locations'] = Location::where('manager_id' ,'=' , Auth::user()->id)->with(['Manager' , 'Dispensers'])->get();
          $response['data']  = $business;
        }
          
           return response()->json($response ,200); 
    }
    public function getBusinessLocationsWithPrice(Request $request  ){
      $validator = Validator::make($request->all(), [
          "business_id" => "required|exists:businesses,id"
    ]);

    if ($validator->fails()) {

         
          $response['code'] = 400;
          $response['errors'] = $validator->messages()->all();
          return response()->json($response ,400);
    }
      return  $business = Business::find($request->business_id);
      
      
       
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
