<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\Models\Business;
use App\Models\Location;
use App\Models\Supplier;

class SupplierController extends Controller
{
    
    public function getBisinessSupliers(Request $request){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id"
      ]);
    
      if ($validator->fails()) {
    
           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      } 

      $suppliers = Supplier::where('business_id' , '=' , $request->business_id)->get();
      $response['data'] = $suppliers;
      return response()->json($response ,200);
    }
    
    public function addSupplier(Request $request){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id",
            "name" => "required",
            "address" => "required",
            "contact_person_name" => "required",
            "contact_person_number" => "required",
            "account_number" => "required",
            "account_name" => "required",
            "bank_name" => "required",
      ]);
    
      if ($validator->fails()) {
    
           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      } 

      $supplier = new Supplier;
      $supplier->business_id = $request->business_id;
      $supplier->address = $request->address;
      $supplier->name = $request->name;
      $supplier->contact_person_name = $request->contact_person_name;
      $supplier->contact_person_number = $request->contact_person_number;
      $supplier->account_number = $request->account_number;
      $supplier->account_name = $request->account_name;
      $supplier->bank_name = $request->bank_name;
      $supplier->save();
      $response['message'] = "Suplier Added Successfully!!!";
      return response()->json($response ,200);
    }

    public function updateSupplier(Request $request){
        $validator = Validator::make($request->all(), [
            "business_id" => "required|exists:businesses,id",
            "supplier_id" =>"required|exists:suppliers,id",
            "name" => "required",
            "address" => "required",
            "contact_person_name" => "required",
            "contact_person_number" => "required",
            "account_number" => "required",
            "account_name" => "required",
            "bank_name" => "required",
      ]);
    
      if ($validator->fails()) {
    
           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      } 
      $supplier = Supplier::find($request->supplier_id);
      if($supplier->business_id != $request->business_id){
        $response['errors'] = ['supplier does not belong to this business'];
        return response()->json($response ,400);
      }
      $supplier->business_id = $request->business_id;
      $supplier->address = $request->address;
      $supplier->name = $request->name;
      $supplier->contact_person_name = $request->contact_person_name;
      $supplier->contact_person_number = $request->contact_person_number;
      $supplier->account_number = $request->account_number;
      $supplier->account_name = $request->account_name;
      $supplier->bank_name = $request->bank_name;
      $supplier->save();
      $response['message'] = 'supplier Updated Successfully!!!';
        return response()->json($response ,400);
    }
}
