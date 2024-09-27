<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\Models\Business;
use App\Models\Location;
use App\Models\Business_User;
use App\Models\Operation_Cost;
use Carbon\Carbon;

class OperationCostController extends Controller
{
    //
    public function getAllLocationCostSummery(Request $request ){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id"
      ]);

      if ($validator->fails()) {

           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      } 
      $locations = Location::where("business_id" , "=" , $request->business_id)->get();
      foreach($locations as $key => $location){
        $TotalOperationalCost = Operation_Cost::where("location_id" , "=" , $location->id)->get();
        $total = 0;
        foreach($TotalOperationalCost as $cost){
            $total += $cost->amount;
        }
        $locations[$key]["totalCost"] = $total;

        $CurrentMonthsOperationCost = Operation_Cost::where( 'location_id' , '=' , $location->id)->whereBetween('created_at',[Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])->get();
        $total = 0;
        foreach($CurrentMonthsOperationCost as $cost){
            $total += $cost->amount;
        }
        $locations[$key]["CurrentMonthsCost"] = $total;
      }
    
    $response['data'] = $locations;
    return response()->json($response ,200); 
    }
    public function getOperationCost(Request $request , $range = '' ){
        // $request->business_id , $
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id",
            "location_id" => "required|exists:locations,id"
      ]);

      if ($validator->fails()) {

           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }
        if($range == 'current_month') {
            $operationCost = Operation_Cost::where([['business_id' ,'=' , $request->business_id ] , [ 'location_id' , '=' , $request->location_id]])->whereBetween('created_at',[Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])->orderBy('created_at', 'desc')->get();
        }
        else if($range == 'last_month') {
            $operationCost = Operation_Cost::where([['business_id' ,'=' , $request->business_id ] , [ 'location_id' , '=' , $request->location_id]])->whereBetween('created_at',[Carbon::now()->startOfMonth()->subMonth(), Carbon::now()->subMonth()->endOfMonth()])->orderBy('created_at', 'desc')->get();
        }
        else if($range == 'last_quater') {
            $operationCost = Operation_Cost::where([['business_id' ,'=' , $request->business_id ] , [ 'location_id' , '=' , $request->location_id] , ["paid_at",">", Carbon::now()->subMonths(3)]])->orderBy('created_at', 'desc')->get();
        }
        else if($range == 'current_year') {
            $operationCost = Operation_Cost::where([['business_id' ,'=' , $request->business_id ] , [ 'location_id' , '=' , $request->location_id]])->whereYear('created_at' , Carbon::now()->year)->orderBy('created_at', 'desc')->get();
        }
        else{
            $operationCost = Operation_Cost::where([['business_id' ,'=' , $request->business_id ] , [ 'location_id' , '=' , $request->location_id]])->orderBy('created_at', 'desc')->get();

        }
        $response['data'] = $operationCost;
        return response()->json($response ,200); 
    }

    public  function addOperationCost(Request $request){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id",
            "location_id" => "required|exists:locations,id",
            "title" => 'required',
            "amount" => 'required|numeric',
            'description' => 'required',
            'paid_at' => 'required|date'
      ]);

      if ($validator->fails()) {

           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }

      $oprationalCost = new Operation_Cost;
      $oprationalCost->business_id = $request->business_id;
      $oprationalCost->location_id = $request->location_id;
      $oprationalCost->title = $request->title;
      $oprationalCost->amount = $request->amount;
      $oprationalCost->description = $request->description;
      $oprationalCost->paid_at = Carbon::parse($request->paid_at);
      $oprationalCost->paidby_id = Auth::user()->id;
      $oprationalCost->save();

      $response['message'] = "Operation Cost Added Successfully!!!";
        return response()->json($response ,200); 

    }

}
