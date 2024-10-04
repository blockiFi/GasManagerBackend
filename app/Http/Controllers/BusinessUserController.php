<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business_User;
use App\Models\User;
use Validator;
use Str;
class BusinessUserController extends Controller
{
    //
    public function getBusinessUsers(Request $request){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id"
      ]);

      if ($validator->fails()) {

           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }
      
      $businessUsers = Business_User::where("business_id" , "="  , $request->business_id)->with("user")->orderBy('created_at' , 'desc')->get();
      $response['data'] = $businessUsers;
      return response()->json($response ,200); 
    }

    public function AddEmployee(Request $request){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id",
            "name" => "required",
            "email" => "required|unique:users,email",
            "password" => "required"
      ]);

      if ($validator->fails()) {

           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }
      
      $user = new User;
      $recoverykey = Str::random(40);
      $user->remember_token = $recoverykey;
      $user->name = $request->name;
      $user->email = $request->email;
      $user->password = bcrypt($request->password);
       $user->save();

       $BusinessUser = new Business_User;
       $BusinessUser->business_id = $request->business_id;
       $BusinessUser->user_id = $user->id;
       $BusinessUser->save();
       $response['code'] = 200;
       $response['message'] = "User Registered Sucessfully!!!";
        return response()->json($response ,200); 


    }
    public function ResetPassword(Request $request){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id",
            "user_id" => "required|exists:users,id",
            "password" => "required"
      ]);

      if ($validator->fails()) {

           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }
      
      $BusinessUser = Business_User::where('user_id' , '=' , $request->user_id)->first();
      if($BusinessUser){
        if($BusinessUser->business_id != $request->business_id){
            $response['code'] = 400;
            $response['errors'] = ['User does not belong to this business.'];
            return response()->json($response ,400);
        }
      }else{
        $response['code'] = 400;
        $response['errors'] = ['Invalid User'];
        return response()->json($response ,400);
      }
      $user = User::find($request->user_id);
      $user->password = bcrypt($request->password);
      $user->save();
      $response['code'] = 200;
      $response['message'] = "User Password Updated Successfully!!!";
      return response()->json($response ,200); 

    }
}
