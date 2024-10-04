<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\Models\Business;
use App\Models\Location;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Business_Setting;
class SettingsController extends Controller
{
    //
    public function getGeneralSettings($withBusinessSettings = null){
        if($withBusinessSettings){
            $settings = Setting::with(['Settings'])->get();

        }
        else{
            $settings = Setting::get();
        }
        $response['data'] = $settings;
        return response()->json($response ,200);

    }
    
    public function addSeting(Request $request){
        $validator = Validator::make($request->all(), [
            "name" => "required|unique:settings",
            "type" => "required",
            "default" => "required"
      ]);
    
      if ($validator->fails()) {
    
           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }

      if($request->type == 'bool'){
        $type = 'boolean';
      }else if ($request->type == 'number'){
        $type = 'number';
      }
      else{
        $type = 'string';
      }

      $setting = new Setting;
      $setting->name = $request->name;
      $setting->type = $type;
      $setting->default = $request->default;
      $setting->save();
      $response['message'] = "Settings Added Successfully!!!";
        return response()->json($response ,200);
    }

    public function initializeBusinessSettings(Request $request){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id"
      ]);
    
      if ($validator->fails()) {
    
           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      } 
      
      $settings = Setting::all();
      foreach($settings as $setting){
        $settingExist = Business_Setting::where([['setting_id' , '=' , $setting->id] , ['business_id' , '=' , $request->business_id]])->first();
      
        if(!$settingExist){
            $business_setting =  new Business_Setting;
            $business_setting->business_id = $request->business_id;
            $business_setting->setting_id = $setting->id;
            $business_setting->value = $setting->default;
            $business_setting->save();
        }
        
      }
      $response['message'] = "Settings Initialized Successfully!!!";
      return response()->json($response ,200);

    }
    public function setBusinessSetting(Request $request){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id",
            "setting_id" => "required|exists:settings,id",
            "value" => "required"
      ]);
    
      if ($validator->fails()) {
    
           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }  

      $settingExist = Business_Setting::where([['setting_id' , '=' , $request->setting_id] , ['business_id' , '=' , $request->business_id]])->first();
      if($settingExist){
        $settingExist->value = $request->value;
        $settingExist->save();
      }
      else{
        $setting =  new Business_Setting;
      $setting->business_id = $request->business_id;
      $setting->setting_id = $request->setting_id;
      $setting->value = $request->value;
      $setting->save();
      }
      $response['message'] = "Settings Saved Successfully!!!";
      return response()->json($response ,200);
    } 

    public function getBusinessSetings(Request $request){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id",
            
      ]);
    
      if ($validator->fails()) {
    
           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }  
      
      $settings =  Business_Setting::where('business_id' , '=' , $request->business_id)->with('Setting')->get();
      $response['data'] = $settings;
      return response()->json($response ,200);
    }

    public function getUserBusinessSetings(Request $request){
      $validator = Validator::make($request->all(), [
        "business_id" => "required|exists:businesses,id"
        
  ]);

  if ($validator->fails()) {

       
        $response['code'] = 400;
        $response['errors'] = $validator->messages()->all();
        return response()->json($response ,400);
  }  

  $settings = Setting::all();
  foreach($settings as $key => $setting){
    $business_setting = Business_Setting::where( [["business_id" , "=" , $request->business_id] , ["setting_id" , "=" , $setting->id ]] )->first();
    if($business_setting){
      $settings[$key]["value"] = $business_setting;
    }else{
     $business_setting = new  Business_Setting;
     $business_setting->business_id = $request->business_id;
     $business_setting->setting_id  = $setting->id;
     $business_setting->value  = $setting->default;
     $business_setting->save();
     $settings[$key]["value"] = $business_setting;
    }

  }

  $response['data'] = $settings;
  return response()->json($response ,200);

    }

    public function updateUserBusinessSetings(Request $request){
      $validator = Validator::make($request->all(), [
        "business_id" => "required|exists:businesses,id",
        "setting_id" => "required|exists:business__settings,id",
        "value" => "required"

        
  ]);

  if ($validator->fails()) {

       
        $response['code'] = 400;
        $response['errors'] = $validator->messages()->all();
        return response()->json($response ,400);
  }
  
  $business_setting = Business_Setting::find($request->setting_id);
  $business_setting->value = $request->value;
  $business_setting->save();
  $response['message'] = "Settings Updated Successfully!!!";
  return response()->json($response ,200);
    }

}
