<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business;
use App\Models\Business_User;

use Auth;
use Validator;

class BusinessController extends Controller
{
    //
    public function createBusiness(Request $request){

        $validator = Validator::make($request->all(), [
            "name" =>  "required",
            'address' => 'required'
      ]);

      if ($validator->fails()) {

           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }
      $userBusinessExist = Business::where('owner_id' , Auth::user()->id)->first();
      if($userBusinessExist != null) {

        $response['errors'] = ['User Has Already Registered A Business'];
        return response()->json($response ,400); 
      }
      $business = new Business;
      $business->name = $request->name;
      $business->address = $request->address;
      $business->owner_id = Auth::user()->id;
      $business->is_active = true;
      $business->save();
      
      $userBusiness = new Business_User;
      $userBusiness->business_id = $business->id;
      $userBusiness->user_id = Auth::user()->id;
      $userBusiness->save();

      $response['data'] = $business;
      $response['message'] = 'Business Created Successfully!!!';
      return response()->json($response ,200); 

    }

    public function getUserBusiness(){
      $userBusiness = Business_User::where('user_id' , '=' , Auth::user()->id)->with(['Business'])->first();
      $response['data'] =  $userBusiness['business'] ;
      return response()->json($response ,200); 


    }
    public function getUserBusinessWithSales(){
      $userBusiness = Business_User::where('user_id' , '=' , Auth::user()->id)->with([
        'Business','business.Sales' ])->first();
      $response['data'] =  $userBusiness['business']  ;
      return response()->json($response ,200); 
    }

    public function updateBusiness(Request $request){
      $validator = Validator::make($request->all(), [
        "business_id" => "required|exists:businesses,id",
        "name" =>  "required",
        'address' => 'required'
        ]);

        if ($validator->fails()) {

            
              $response['code'] = 400;
              $response['errors'] = $validator->messages()->all();
              return response()->json($response ,400);
        }
        

        $business = Business::find($request->business_id);

        if($business->owner_id != Auth::user()->id){
          $res['errors'] = ["Unauthorised Access"];
          $res['code'] = 401;
          return response()->json($res ,401);
        }
        $business->name =  $request->name;
        $business->address =  $request->address;
        $business->save();

        $response['data'] = $business;
        $response['message'] = 'Business Updated Successfully!!!';
        return response()->json($response ,200); 
    }
   

}
