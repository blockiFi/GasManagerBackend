<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Auth;
use Validator;
use App\Models\Business;
use App\Models\Location;
use App\Models\Sale;
use App\Models\Price;
use Carbon\Carbon;
use App\Models\Sale_Reciept;
use App\Models\Supply;
use App\Models\Setting;
use App\Models\Business_Setting;
use App\Models\Dispenser;
use App\Http\Requests\GetSalesGroupedByWeeksRequest;
use App\Services\SalesGroupedAnalyticsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;

class SalesController extends Controller
{
    /**
     * Sale whose closing readings become the next record's opening (meter chain).
     * Uses calendar sales_date (then id), not insertion order alone, so backdated
     * and bulk entries still chain to the correct prior reading.
     */
    protected function previousSaleForDispenser(
        string $businessId,
        string $locationId,
        string $dispenserId,
        Carbon $salesDate
    ): ?Sale {
        $day = $salesDate->copy()->startOfDay();

        $base = Sale::query()
            ->where('business_id', '=', $businessId)
            ->where('location_id', '=', $locationId)
            ->where('dispenser_id', '=', $dispenserId);

        $sameDay = (clone $base)
            ->whereDate('sales_date', '=', $day)
            ->orderByDesc('id')
            ->first();

        if ($sameDay) {
            return $sameDay;
        }

        return (clone $base)
            ->whereDate('sales_date', '<', $day)
            ->orderByDesc('sales_date')
            ->orderByDesc('id')
            ->first();
    }

    public function getSalesGroupedByWeeks(GetSalesGroupedByWeeksRequest $request)
    {
        $result = app(SalesGroupedAnalyticsService::class)->buildGroupedSales($request);
        if (! $result['success']) {
            return response()->json([
                'code' => $result['code'],
                'errors' => $result['errors'],
            ], $result['code']);
        }

        return response()->json([
            'data' => $result['data'],
            'code' => 200,
        ], 200);
    }

    public function getSalesProfit($supply_id)
    {
        if ($denied = $this->denyUnlessCanAccessSupplyForUser($supply_id)) {
            return $denied;
        }
        $sales = Sale::where('supply_id', '=', $supply_id)->get();
        $data = $this->getProfit($sales);
        $data['sales'] = $sales;

        return $data;
    }
    public function getMonthSales(Request $request)
    {
    $validator = Validator::make($request->all(), [
        "business_id" => "required|exists:businesses,id",
        "location_id" => "required|exists:locations,id",
        "month" => "required",
        "year" => "required"
  ]);

  if ($validator->fails()) {

       
        $response['code'] = 400;
        $response['errors'] = $validator->messages()->all();
        return response()->json($response ,400);
  }
    if ($denied = $this->denyUnlessCanAccessBusinessAndLocation($request)) {
        return $denied;
    }
        $from = Carbon::create((int) $request->year, (int) $request->month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth();
        $sales = Sale::where([['business_id' , '=' , $request->business_id] , ['location_id' , '=' , $request->location_id] ])->whereBetween('sales_date', [$from->toDateString(), $to->toDateString()])->get();
    $result = $this->getProfit($sales);           
                foreach($sales as $key => $sale){
                    $sales[$key]['average_price'] = $sale->kg_quantity
                        ? $sale->amount / $sale->kg_quantity
                        : null;

                }

        $supply = Supply::where([['business_id' , '=' , $request->business_id] , ['location_id' , '=' , $request->location_id] , ['sold' ,'=' , '1']])->whereBetween('updated_at', [$from, $to])->get();
        $profitfromExcess  = 0 ;
        $excessKg =0;
        foreach($supply as $key => $sup){
            $excessKg += $sup->excess_kg ;
            $supQty = (float) $sup->quantity;
            $profit = $supQty > 0 ? $sup->excess_kg * $sup->amount / $supQty : 0;
            $profitfromExcess = $profitfromExcess + $profit;
        }
        
        $data['amount'] = $result['totalSales'] ;
        $data['kg'] = + $result['totalKg'] ;
        $data['profit'] = $result['profit'] ;
        $data['profitfromExcess'] = $profitfromExcess;
        $data['excessKg'] = $excessKg;
        $data['totalProfit'] = $result['profit'] + $profitfromExcess;
        $data['sales'] = $sales ;
        $data['suppplies'] = $supply;
        return  $data;
    }

    public function getDailySales(Request $request){
    $validator = Validator::make($request->all(), [
        "business_id" => "required|exists:businesses,id",
        "location_id" => "required|exists:locations,id",
        "dispenser_id" => "required|exists:dispensers,id",
        "date" => "required|date"
  ]);

  if ($validator->fails()) {

       
        $response['code'] = 400;
        $response['errors'] = $validator->messages()->all();
        return response()->json($response ,400);
  }
    if ($denied = $this->denyUnlessCanAccessBusinessAndLocation($request)) {
        return $denied;
    }
    $from = date($request->date);
    $to = date($request->date);
    $sales = Sale::where([['business_id' , '=' , $request->business_id] , ['location_id' , '=' , $request->location_id] , ['dispenser_id' , '=' , $request->dispenser_id]])->whereBetween('sales_date', [$from, $to])->get();
    $result = $this->getProfit($sales);           
                foreach($sales as $key => $sale){
                    $sales[$key]['average_price'] =$sale->amount / $sale->kg_quantity;

                }
        $data['amount'] = $result['totalSales'] ;
        $data['kg'] = $result['totalKg'] ;
        $data['profit'] = $result['profit'] ;
        $data['sales'] = $sales ;
        return  $data;
}

public function getSalesBetween(Request $request){
    $validator = Validator::make($request->all(), [
        "date1" => "required",
        "date2" => "required",
        "location_id" => "required"
  ]);

  if ($validator->fails()) {

       
        $response['code'] = 400;
        $response['errors'] = $validator->messages()->all();
        return response()->json($response ,400);
  }

    $location = Location::find($request->location_id);
    if (! $location) {
        return response()->json(['code' => 404, 'errors' => ['Location not found.']], 404);
    }
    if (! $this->businessAuth()->userCanAccessLocation(Auth::user(), $location->business_id, $location->id)) {
        return response()->json(['code' => 403, 'errors' => ['You do not have access to this location.']], 403);
    }

   $from = date($request->date1);
  $to = date($request->date2);
    $sales = Sale::where('location_id' ,'=' , $request->location_id)->whereBetween('sales_date', [$from, $to])->get();
     $result = $this->getProfit($sales);           
                foreach($sales as $key => $sale){
                    $sales[$key]['average_price'] =$sale->amount / $sale->kg_quantity;

                }
        $data['amount'] = $result['totalSales'] ;
        $data['kg'] = $result['totalKg'] ;
        $data['profit'] = $result['profit'] ;
        $data['sales'] = $sales ;
        return  $data;
}
public function getProfit($sales = []) {
    $totalSales = 0;
    $totalKg = 0;
    $Profit = 0;

    if(count($sales) != 0 ){
        
        foreach($sales as $key => $sale){
        $supply = Supply::find($sale->supply_id);

        $qty = $supply ? (float) $supply->quantity : 0.0;
        $unitPrice = $supply
            ? ($qty > 0 ? (float) $supply->amount / $qty : 0.0)
            : 1.0;
        $cost = $unitPrice * $sale->kg_quantity;
        $totalSales = $totalSales + $sale->amount;
        $totalKg = $totalKg + $sale->kg_quantity;
        $dailyProfit = $sale->amount - $cost;
        $Profit = $Profit + $dailyProfit;
        $sales[$key]['dailyProfit'] = $dailyProfit;
    }
    }

    $data['totalSales'] = $totalSales;
    $data['Number of Days'] =count($sales);
    $data['totalKg'] = $totalKg;
    $data['profit'] = $Profit;
        
        return $data;

}

    public function getSalesBreakdown(Request $request) {
    $validator = Validator::make($request->all(), [
        "business_id" => "required|exists:businesses,id"
  ]);

  if ($validator->fails()) {

       
        $response['code'] = 400;
        $response['errors'] = $validator->messages()->all();
        return response()->json($response ,400);
  }
    if ($denied = $this->denyUnlessCanAccessBusiness($request)) {
        return $denied;
    }
    $locationsQuery = Location::where('business_id', '=', $request->business_id);
    $restrictedIds = $this->businessAuth()->userAccessibleLocationIds(Auth::user(), $request->business_id);
    if ($restrictedIds !== null) {
        if ($restrictedIds === []) {
            $locations = collect();
        } else {
            $locations = $locationsQuery->whereIn('id', $restrictedIds)->get();
        }
    } else {
        $locations = $locationsQuery->get();
    }
  foreach($locations as $key => $location){
    $sales = Sale::where([['business_id' , '=' , $request->business_id] , ['location_id' , '=' , $location->id]])->get();
    $totalSaleData  =  $this->getProfit($sales);
    $CurrentMonthSales = Sale::where([['business_id' , '=' , $request->business_id] , 
    ['location_id' , '=' , $location->id]])->whereBetween('sales_date', 
    [
        Carbon::now()->startOfMonth(), 
        Carbon::now()->endOfMonth()
    ])->get();
    $CurrentMonthSalesData  =  $this->getProfit($CurrentMonthSales);
    
    
    //get  supplies 
    $supply = Supply::where([['business_id' , '=' , $request->business_id] , ['location_id' , '=' , $location->id] , ['sold' ,'=' , '1']])->get();
    //  sum  excess kg
    $excessKg = 0;
    $excessPorifit = 0;
    foreach($supply as $sup){
        $excessKg = $excessKg + $sup->excess_kg;
        $profit = $sup->excess_kg * $sup->amount / $sup->quantity;
        $excessPorifit = $excessPorifit + $profit;
    }

    $locations[$key]['totalSales'] = $totalSaleData['totalSales'];
    $locations[$key]['totalProfit'] = $totalSaleData['profit'];
    $locations[$key]["totalSalesData"] = $totalSaleData;
    $locations[$key]["currentMonthSalesData"] = $CurrentMonthSalesData;
    $locations[$key]["totalExcessKg"] = $excessKg;
    $locations[$key]["totalExcessProfit"] = $excessPorifit;

    
  }
  
  $response['data'] =$locations;
return response()->json($response ,200);
}
public function getLocationSales(Request $request , $dispenser = null ){
    $validator = Validator::make($request->all(), [
        "business_id" => "required|exists:businesses,id",
        "location_id" => "required|exists:locations,id",
        
  ]);

  if ($validator->fails()) {

       
        $response['code'] = 400;
        $response['errors'] = $validator->messages()->all();
        return response()->json($response ,400);
  }
    if ($denied = $this->denyUnlessCanAccessBusinessAndLocation($request)) {
        return $denied;
    }
if($dispenser){
    if ($request->has('count')) {
        $count = intval($request->input('count'));
        $sales = Sale::where([
                ['business_id', '=', $request->business_id],
                ['location_id', '=', $request->location_id],
                ['dispenser_id', '=', $dispenser]
            ])
            ->with(['Price', 'Dispenser'])
            ->orderBy('sales_date', 'desc')
            ->take($count)
            ->get();
    } else {
        $sales = Sale::where([
            ['business_id', '=', $request->business_id],
            ['location_id', '=', $request->location_id],
            ['dispenser_id', '=', $dispenser]
        ])
        ->with(['Price', 'Dispenser'])
        ->get();
    }

    
   
}else{
    if ($request->has('count')) {
        $count = intval($request->input('count'));
        $sales = Sale::where([
                ['business_id', '=', $request->business_id],
                ['location_id', '=', $request->location_id]
            ])
            ->with(['Price', 'Dispenser', 'supply'])
            ->orderBy('sales_date', 'desc')
            ->take($count)
            ->get();
    } else {
        $sales = Sale::where([
                ['business_id', '=', $request->business_id],
                ['location_id', '=', $request->location_id]
            ])
            ->with(['Price', 'Dispenser', 'supply'])
            ->get();
    }


}
foreach($sales as $key => $sale){

    $unitPrice =  $sale->kg_quantity == 0 ? 0 : $sale->amount / $sale->kg_quantity;
    $sales[$key]["average_price"] = $unitPrice;
    if(!$sale->supply){
        return response()->json([
            'code' => 422,
            'errors' => ['A sale record is missing supply linkage.'],
        ], 422);
    }
    $sales[$key]["supply"] =  $sale->supply;
    
    $sales[$key]["expected_sales_amount"] = $sale->kg_quantity * $sale->Price->price;
    $priceDif =     ((($unitPrice)) - ($sale->supply['amount'] /$sale->supply['quantity']));
    $sales[$key]["profit"]  = $priceDif * $sale->kg_quantity;

     

}


$location = Location::find($request->location_id);
$response['data'] =$sales;
$response['location'] =$location;
return response()->json($response ,200);
}

public function addSales(Request $request){
    $validator = Validator::make($request->all(), [
        "business_id" => "required|exists:businesses,id",
        "location_id" => "required|exists:locations,id",
        "dispenser_id" => "required|exists:dispensers,id",
        "opening_sales" => "required|numeric",
        "closing_sales" => "required|numeric",
        "opening_kg" => "required|numeric",
        "closing_kg" => "required|numeric",
        "sales_date" => "required|date"


  ]);

  if ($validator->fails()) {

       
        $response['code'] = 400;
        $response['errors'] = $validator->messages()->all();
        return response()->json($response ,400);
  }
    if ($denied = $this->denyUnlessCanAccessBusinessAndLocation($request)) {
        return $denied;
    }
 $supply  = Supply::where([['business_id' ,'=' , $request->business_id ] ,['location_id' ,'=' , $request->location_id ] ,['dispenser_id' ,'=' , $request->dispenser_id ], ['sold' ,'=' , '0' ]])->first();
  if($supply){

  }else{
    $supply  = Supply::where([['business_id' ,'=' , $request->business_id ] ,['location_id' ,'=' , $request->location_id ] ,['dispenser_id' ,'=' , $request->dispenser_id ]])->latest()->first();
  
  }
   $salesDate = Carbon::parse($request->sales_date);
    $prevSales = $this->previousSaleForDispenser(
       (string) $request->business_id,
       (string) $request->location_id,
       (string) $request->dispenser_id,
       $salesDate
   );
   $price = Price::where([['business_id' ,'=' , $request->business_id] , ['location_id' , '=' , $request->location_id] , ['active' , '=' , 'true']])->first();
  if(!$price){
    $response['code'] = 400;
    $response['errors'] = ["No Price Available Please set sale Price"];
    return response()->json($response ,400);    
    }
   if($prevSales){

  $response =  $this->ValidateSales($request , $prevSales , $price);
    if($response['code'] == 400){
        return response()->json($response ,400);
    }
    
    $sales = new Sale;
    $sales->business_id = $request->business_id;
    $sales->location_id = $request->location_id;
    $sales->dispenser_id = $request->dispenser_id;
    $sales->opening_sales = $prevSales->closing_sales;
    $sales->closing_sales = $request->closing_sales;
    $sales->opening_kg = $prevSales->closing_kg;
    $sales->closing_kg = $request->closing_kg;

    if((float)$request->closing_sales >= (float)$prevSales->closing_sales){
        $sales->amount = (float)$request->closing_sales - (float)$prevSales->closing_sales;
    }else{
        $sales->amount = (1000000 + (float)$request->closing_sales) - (float)$prevSales->closing_sales;
    }

    $sales->kg_quantity = (float)$request->closing_kg - (float)$prevSales->closing_kg;
    $sales->sales_date = $salesDate;
    $sales->uploaded_by = Auth::user()->id;
    $sales->price_id  = $price->id;
    $sales->supply_id = $supply->id;
    $sales->save();
  }else{
    $prevSales = new Sale;
    $prevSales->business_id = $request->business_id;
    $prevSales->location_id = $request->location_id;
    $prevSales->dispenser_id = $request->dispenser_id;
    $prevSales->opening_sales = 0;
    $prevSales->closing_sales = $request->opening_sales;
    $prevSales->opening_kg = 0;
    $prevSales->closing_kg = $request->opening_kg;;

    

    $response =  $this->ValidateSales($request , $prevSales , $price);
    if($response['code'] == 400){
        return response()->json($response ,400);
    }
    
    $sales = new Sale;
    $sales->business_id = $request->business_id;
    $sales->location_id = $request->location_id;
    $sales->dispenser_id = $request->dispenser_id;
    $sales->opening_sales = $request->opening_sales;
    $sales->closing_sales = $request->closing_sales;
    $sales->opening_kg = $request->opening_kg;
    $sales->closing_kg = $request->closing_kg;

    if((float)$request->closing_sales > (float)$request->opening_sales){
        $sales->amount = (float)$request->closing_sales - (float)$request->opening_sales;
    }else{
        $sales->amount = (10000000 + (float)$request->closing_sales) - (float)$request->opening_sales;
    }
    
    $sales->kg_quantity = (float)$request->closing_kg - (float)$request->opening_kg;;
    $sales->sales_date = $salesDate;
    $sales->uploaded_by = Auth::user()->id;
    $sales->price_id  = $price->id;
    $sales->supply_id = $supply->id;
    $sales->save();
  
  }
  $dispenser = Dispenser::find($request->dispenser_id);
   $dispenser->prev_level = $dispenser->current_level;
  if($sales->kg_quantity > $dispenser->current_level){
    $dispenser->current_level = 0;

  }else{
   
    $dispenser->current_level = $dispenser->current_level -  $sales->kg_quantity;
    
  }

if($supply->sold == 1){

      $_profit =$sales->amount - ($sales->kg_quantity * $supply->amount / $supply->quantity); 
      $supply->profit = $supply->profit + $_profit;
      $supply->excess_kg =  $supply->excess_kg + $sales->kg_quantity;
     $supply->save();
}
else{
    
    if($sales->kg_quantity > $supply->available_quantity){
        $remainingKg = $sales->kg_quantity -  $supply->available_quantity;
        
                if($dispenser->empty_sale == 'true'){
                    $supply->available_quantity  = 0;
                    $supply->sold = 1;
                    $sales = Sale::where('supply_id' , '=' , $supply->id)->get();
                    $totalSales = 0;
                    foreach($sales as $sale){
                        $totalSales = $totalSales + $sale->amount;
                  
                    }
                  $supply->profit =   $totalSales - $supply->amount;
                  $supply->excess_kg =   $remainingKg;
                  $supply->save();
    
                }
    
                else{
                    $supply->prev_quantity  = $supply->available_quantity ;
                    $supply->available_quantity  = 0;
                    $supply->sold = 1;
                    $supply->save();
                    $newSupply  = Supply::where([['business_id' ,'=' , $request->business_id ] ,['location_id' ,'=' , $request->location_id ] ,['dispenser_id' ,'=' , $request->dispenser_id ], ['sold' ,'=' , '0' ]])->first();
                    if($newSupply){
                    $newSupply->available_quantity = $newSupply->available_quantity - $remainingKg;
                     $newSupply->save();
                    }
                }
            
        
    
      }else{
        $supply->prev_quantity  = $supply->available_quantity ;
        $supply->available_quantity = $supply->available_quantity -  $sales->kg_quantity;
        $supply->save();
        
      }
}
//   $sales->supply_id = $supply->id;
  
  $dispenser->save();
  $response['message'] = "Sales Added Successfully!!!";
return response()->json($response ,200);
}
public function ValidateSales($request , $prevSales , $price){
    $saleskg = $request->closing_kg - $prevSales->closing_kg;

    if((float)$request->closing_sales >= (float)$prevSales->closing_sales){
        $salesamount = (float)$request->closing_sales - (float)$prevSales->closing_sales;
    }else{
        $salesamount = (1000000 + (float)$request->closing_sales) - (float)$prevSales->closing_sales;
    }
    if($saleskg < 0){
        $response['code'] = 400;
        $response['errors'] = ["Sales Kg is negative, meaning the data uplaoded is not valid"];
        return $response;
    }
    if($salesamount < 0){
        $response['code'] = 400;
        $response['errors'] = ["Sales Kg is negative, meaning the data uplaoded is not valid"];
        return $response;
    }
    if($saleskg == 0){
        $response['code'] = 400;
        $response['errors'] = ["Sales Kg is 0, meaning no sales made"];
        return $response;
    }
    if($salesamount == 0){
        $response['code'] = 400;
        $response['errors'] = ["Sales Amount is 0,  meaning no sales made"];
        return $response;
    }
    $salesPrice =  $salesamount / $saleskg;
   
    $PriceThresholdSetting = Setting::where('name' , 'sales_price_threshold')->first();
    $priceThreshold =  $PriceThresholdSetting->default;
     $businessSetting = Business_Setting::where([['business_id' , '=' , $request->business_id] , ['setting_id' , '=' , $PriceThresholdSetting->id ]])->first();
  
    if($businessSetting){
        $priceThreshold = $businessSetting->value;
    }
    if($salesPrice !=  $price->price){
        $priceDifference = abs($salesPrice - $price->price);
        if($salesPrice > $price->price && $priceDifference > $priceThreshold){
            $response['code'] = 400;
            $response['errors'] = [
                "Sales Price is higher than the current price",
                "$price->price is the current price",
                " $salesPrice is the sales price",
                " $priceDifference is the difference",
                "$priceThreshold  is the price threshold"
            ];
           
        }
        else if($salesPrice < $price->price && $price->price - $salesPrice > $priceThreshold){
            $priceDifference = abs($salesPrice - $price->price);
            $response['code'] = 400;
            $response['errors'] = ["Sales Price is lower than the current price",
            "$price->price is the current price",
            " $salesPrice is the sales price",
            " $priceThreshold  is the price threshold",
            " $priceDifference is the difference"];
            
            
        }
        else {
            $response['code'] = 200;
            $response['message'] = "Sales Price is Valid";
        }
        
        
           
    }
    else{
        $response['code'] = 200;
        $response['message'] = "Sales Price is Valid";
    }

    return $response;

}
public function  uploadReciept(Request $request){
    $validator = Validator::make($request->all(), [
        "business_id" => "required|exists:businesses,id",
        "sales_id" => "required|exists:sales,id",
        "location_id" => "required|exists:locations,id",
        'files.*' => 'required|file|mimes:jpeg,png,jpg'
  ]);

  if ($validator->fails()) {

       
        $response['code'] = 400;
        $response['errors'] = $validator->messages()->all();
        return response()->json($response ,400);
  }
    if ($denied = $this->denyUnlessCanAccessBusinessAndLocation($request)) {
        return $denied;
    }

        $sales = Sale::find($request->sales_id);
        if (! $sales || (string) $sales->business_id !== (string) $request->business_id
            || (string) $sales->location_id !== (string) $request->location_id) {
            return response()->json(['code' => 403, 'errors' => ['Sale does not match this business or location.']], 403);
        }
        $filePaths = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('receipts', 'public'); // Store file in the "public/uploads" directory
                $filePaths[] = $path;
                $reciept =  new Sale_Reciept;
                $reciept->sales_id = $request->sales_id;
                $reciept->image_path = $path;
                $reciept->save();
            }
            $sales->status = "confirming";
            $sales->save();
            $response['message'] = 'Sales Reciepts Added Successfully!!!';
            $response['file_paths'] = $filePaths;
            return response()->json($response ,200);
        }
        else{
                $response['code'] = 400;
                $response['errors'] = ["Receipt image not available"];
                return response()->json($response ,400);
            }
    
}

public function getSalesReceipts(Request $request){
    $validator = Validator::make($request->all(), [
        "business_id" => "required|exists:businesses,id",
        "sales_id" => "required|exists:sales,id",
  ]);

  if ($validator->fails()) {

       
        $response['code'] = 400;
        $response['errors'] = $validator->messages()->all();
        return response()->json($response ,400);
  }
    if ($denied = $this->denyUnlessCanAccessBusiness($request)) {
        return $denied;
    }
    if ($denied = $this->denyUnlessCanAccessSaleForUser($request->sales_id)) {
        return $denied;
    }

  $receipt = Sale_Reciept::where("sales_id" , "=" , $request->sales_id)->get();
            $response['data'] = $receipt;
            return response()->json($response ,200);
}

public function confirmSales(Request $request){
        $validator = Validator::make($request->all(), [
                "business_id" => "required|exists:businesses,id",
                "location_id" => "required|exists:locations,id",
                "sales_id" => "required|exists:sales,id",
        ]);

        if ($validator->fails()) {

            
                $response['code'] = 400;
                $response['errors'] = $validator->messages()->all();
                return response()->json($response ,400);
        } 
  
        $business = Business::find($request->business_id);
        if(Auth::user()->id != $business->owner_id){
            $response['code'] = 400;
                $response['errors'] = ["Only Business Owner can Confirm Payment"];
                return response()->json($response ,400);
        }

        $sales = Sale::find($request->sales_id);
        if (! $sales || (string) $sales->business_id !== (string) $request->business_id
            || (string) $sales->location_id !== (string) $request->location_id) {
            return response()->json(['code' => 403, 'errors' => ['Sale does not match this business or location.']], 403);
        }
        $sales->status = "confirmed";
        $sales->save();
        $response['code'] = 200;
        $response['message'] = "Sale Payment Confirmed Successfully!!!";
        return response()->json($response ,200);
}

public function editSaleDate(Request $request)
{
    $validator = Validator::make($request->all(), [
        'business_id' => 'required|exists:businesses,id',
        'location_id' => 'required|exists:locations,id',
        'sales_id' => 'required|exists:sales,id',
        'sales_date' => 'required|date',
    ]);

    if ($validator->fails()) {
        $response['code'] = 400;
        $response['errors'] = $validator->messages()->all();

        return response()->json($response, 400);
    }

    if ($denied = $this->denyUnlessCanAccessBusinessAndLocation($request)) {
        return $denied;
    }

    $sale = Sale::find($request->sales_id);
    if (! $sale || (string) $sale->business_id !== (string) $request->business_id
        || (string) $sale->location_id !== (string) $request->location_id) {
        return response()->json(['code' => 403, 'errors' => ['Sale does not match this business or location.']], 403);
    }

    $oldDate = $sale->sales_date;
    $sale->sales_date = Carbon::parse($request->sales_date);
    $sale->save();

    Log::info('Sale date updated', [
        'sale_id' => $sale->id,
        'business_id' => $sale->business_id,
        'location_id' => $sale->location_id,
        'user_id' => Auth::user()?->id,
        'old_sales_date' => $oldDate,
        'new_sales_date' => (string) $sale->sales_date,
    ]);

    return response()->json([
        'code' => 200,
        'message' => 'Sale date updated successfully.',
        'data' => $sale,
    ], 200);
}

    public function reverseLatestSale(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_id' => 'required|exists:businesses,id',
            'location_id' => 'required|exists:locations,id',
            'dispenser_id' => 'required|exists:dispensers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 400,
                'errors' => $validator->messages()->all(),
            ], 400);
        }

        if ($denied = $this->denyUnlessCanAccessBusinessAndLocation($request)) {
            return $denied;
        }

        try {
            return DB::transaction(function () use ($request) {
                $sale = Sale::query()
                    ->where('business_id', '=', $request->business_id)
                    ->where('location_id', '=', $request->location_id)
                    ->where('dispenser_id', '=', $request->dispenser_id)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if (! $sale) {
                    return response()->json([
                        'code' => 404,
                        'errors' => ['No sale found for this dispenser.'],
                    ], 404);
                }

                $dispenser = Dispenser::query()->whereKey($sale->dispenser_id)->lockForUpdate()->first();
                if (! $dispenser) {
                    return response()->json([
                        'code' => 404,
                        'errors' => ['Dispenser not found.'],
                    ], 404);
                }

                $supply = Supply::query()->whereKey($sale->supply_id)->lockForUpdate()->first();
                if (! $supply) {
                    return response()->json([
                        'code' => 404,
                        'errors' => ['Supply not found for this sale.'],
                    ], 404);
                }

                $saleId = $sale->id;
                $kg = (float) $sale->kg_quantity;
                $qty = max((float) $supply->quantity, 1.0);

                if ((int) $supply->sold === 0) {
                    $supply->available_quantity = (int) round((float) $supply->available_quantity + $kg);
                    $supply->prev_quantity = $supply->available_quantity;
                    $supply->save();
                } else {
                    $otherKgSum = (float) Sale::query()
                        ->where('supply_id', '=', $supply->id)
                        ->where('id', '!=', $sale->id)
                        ->sum('kg_quantity');

                    if ($otherKgSum >= $qty) {
                        $unitCost = (float) $supply->amount / $qty;
                        $saleProfit = (float) $sale->amount - ($kg * $unitCost);
                        $supply->excess_kg = max(0, (float) $supply->excess_kg - $kg);
                        $supply->profit = (float) $supply->profit - $saleProfit;
                        $supply->save();
                    } else {
                        if ($kg > (float) $supply->prev_quantity) {
                            throw new HttpResponseException(response()->json([
                                'code' => 409,
                                'errors' => ['Latest sale spilled into a later supply and cannot be reversed safely. Please adjust supplies manually.'],
                            ], 409));
                        }
                        $supply->sold = 0;
                        $supply->available_quantity = (int) $supply->prev_quantity;
                        $supply->excess_kg = 0;
                        $supply->profit = 0;
                        $supply->save();
                    }
                }

                $restored = (float) $dispenser->prev_level;
                $dispenser->current_level = $restored;
                $dispenser->prev_level = $restored;
                $dispenser->save();

                $receipts = Sale_Reciept::where('sales_id', '=', (string) $sale->id)->get();
                foreach ($receipts as $reciept) {
                    if ($reciept->image_path) {
                        Storage::disk('public')->delete($reciept->image_path);
                    }
                    $reciept->delete();
                }

                $sale->delete();

                Log::info('Sale reversed', [
                    'sale_id' => $saleId,
                    'business_id' => $request->business_id,
                    'location_id' => $request->location_id,
                    'dispenser_id' => $request->dispenser_id,
                    'user_id' => Auth::user()?->id,
                ]);

                return response()->json([
                    'code' => 200,
                    'message' => 'Latest sale reversed; dispenser level and supply remaining restored.',
                ], 200);
            });
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }
    }
}
