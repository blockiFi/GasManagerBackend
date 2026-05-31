<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\Models\Business;
use App\Models\Dispenser;
use App\Models\Supply;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Sale;

class SupplyController extends Controller
{
    //
    public function getSupplies(Request $request , $location = null , $dispenser = null){
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
        $restrictedIds = $this->businessAuth()->userAccessibleLocationIds(Auth::user(), $request->business_id);
      if($location){
        if ($restrictedIds !== null && ! in_array((string) $location, $restrictedIds, true)) {
            return response()->json(['code' => 403, 'errors' => ['You do not have access to this location.']], 403);
        }
        if($dispenser){
        $supplies =  Supply::where([['business_id' , '=' , $request->business_id] , ['location_id' , '=' , $location] , ['dispenser_id' , '=' , $dispenser]])->with(['Dispenser' , 'Location' , 'Supplier' , 'Reciever'] )->get();
        
        }
        else{
        $supplies =  Supply::where([['business_id' , '=' , $request->business_id] , ['location_id' , '=' , $location]])->with(['Dispenser' , 'Location' , 'Supplier' , 'Reciever'] )->get();

        }
      }
      else{
        $q = Supply::where('business_id' , '=' , $request->business_id);
        if ($restrictedIds !== null) {
            if ($restrictedIds === []) {
                $supplies = collect();
            } else {
                $supplies = $q->whereIn('location_id', $restrictedIds)->with(['Dispenser' , 'Location' , 'Supplier' , 'Reciever'] )->get();
            }
        } else {
            $supplies = $q->with(['Dispenser' , 'Location' , 'Supplier' , 'Reciever'] )->get();
        }


      }
      $response['data'] = $supplies;
      return response()->json($response ,200);
    }

    public function getSupplyDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_id' => 'required|exists:businesses,id',
            'supply_id' => 'required|exists:supplies,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 400,
                'errors' => $validator->messages()->all(),
            ], 400);
        }

        if ($denied = $this->denyUnlessCanAccessBusiness($request)) {
            return $denied;
        }
        if ($denied = $this->denyUnlessCanAccessSupplyForUser($request->supply_id)) {
            return $denied;
        }

        $supply = Supply::with([
            'Dispenser',
            'Location',
            'Supplier',
            'Reciever',
            'Sales.Price',
            'Sales.Dispenser',
        ])->find($request->supply_id);

        if (! $supply || (string) $supply->business_id !== (string) $request->business_id) {
            return response()->json([
                'code' => 404,
                'errors' => ['Supply not found for this business.'],
            ], 404);
        }

        $sales = $supply->Sales ?? collect();

        $totalSalesAmount = 0.0;
        $totalKgSold = 0.0;
        $salesProfit = 0.0;

        $unitCost = $supply->unitCost();

        foreach ($sales as $sale) {
            $kg = (float) ($sale->kg_quantity ?? 0);
            $amt = (float) ($sale->amount ?? 0);

            $totalKgSold += $kg;
            $totalSalesAmount += $amt;

            if ($sale->profit !== null) {
                $salesProfit += (float) $sale->profit;
            } else {
                $salesProfit += $amt - ($unitCost * $kg);
            }
        }

        $excessKg = (float) ($supply->excess_kg ?? 0);
        $excessProfit = $unitCost > 0 ? ($excessKg * $unitCost) : 0.0;

        return response()->json([
            'code' => 200,
            'data' => [
                'supply' => $supply,
                'sales' => $sales,
                'totals' => [
                    'quantity' => (float) ($supply->quantity ?? 0),
                    'quantity_left' => (float) ($supply->available_quantity ?? 0),
                    'total_kg_sold' => $totalKgSold,
                    'total_sales_amount' => $totalSalesAmount,
                    'sales_profit' => $salesProfit,
                    'excess_kg' => $excessKg,
                    'excess_profit' => $excessProfit,
                    'total_profit' => $salesProfit + $excessProfit,
                    'unit_cost' => $unitCost,
                ],
            ],
        ], 200);
    }

    public function addSupply(Request $request){
        $isUnlimited = filter_var($request->input('unlimited'), FILTER_VALIDATE_BOOLEAN);

        $rules = [
            'business_id' => 'required|exists:businesses,id',
            'location_id' => 'required|exists:locations,id',
            'dispenser_id' => 'required|exists:dispensers,id',
            'supplier_id' => 'required',
            'purchased_at' => 'nullable|date',
        ];

        if ($isUnlimited) {
            $rules['unit_cost'] = 'required|numeric|min:0';
        } else {
            $rules['quantity'] = 'required';
            $rules['amount'] = 'required';
        }

        $validator = Validator::make($request->all(), $rules);
    
      if ($validator->fails()) {
    
           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }
        if ($denied = $this->denyUnlessCanAccessBusinessAndLocation($request)) {
            return $denied;
        }

      $supply =  new Supply;
      $supply->business_id = $request->business_id;
      $supply->location_id = $request->location_id;
      $supply->dispenser_id = $request->dispenser_id;
      $supply->supplier_id = $request->supplier_id;
      $supply->sold = 0;
      $supply->profit = 0;
      $supply->excess_kg = 0;
      $supply->purchased_at = Carbon::parse($request->input('purchased_at', now()));

      if ($isUnlimited) {
          $supply->unlimited = true;
          $supply->unit_cost = (string) $request->unit_cost;
          $supply->quantity = '0';
          $supply->available_quantity = 0;
          $supply->amount = '0';
          $supply->supplied = true;
          $supply->delivered_at = Carbon::now();
      } else {
          $supply->unlimited = false;
          $supply->quantity = $request->quantity;
          $supply->available_quantity = $request->quantity;
          $supply->amount = $request->amount;
      }

      $supply->save();
      $response['message'] = "Supply Added Successfully!!!";
      return response()->json($response ,200);
    }
    public function confirmSupply(Request $request){
        $validator = Validator::make($request->all(), [
            'business_id' => 'required|exists:businesses,id',
            'supply_id' => 'required|exists:supplies,id',
            'note' => 'nullable|string',
            'delivered_at' => 'nullable|date',
        ]);
    
      if ($validator->fails()) {
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }
        if ($denied = $this->denyUnlessCanAccessBusiness($request)) {
            return $denied;
        }
        if ($denied = $this->denyUnlessCanAccessSupplyForUser($request->supply_id)) {
            return $denied;
        }

      $supply = Supply::find($request->supply_id);
      if($request->business_id != $supply->business_id){
        $response['errors'] = ['This supply does not belong to this business'];
        return response()->json($response ,400);
      }
      $supply->recieved_by = Auth::user()->id;
      $supply->note = $request->note;
      $supply->delivered_at = Carbon::parse($request->input('delivered_at', now()));
      $supply->supplied = true;
      $supply->save();

      $dispenser  = Dispenser::find($supply->dispenser_id);
      $dispenser->current_level =  (float)$dispenser->current_level + (float)$supply->quantity;
      $dispenser->save();
      $response['message'] = "Supply Updated Successfully!!!";
      return response()->json($response ,200);

    }

    /**
     * Mark an active supply as closed. Any unsold batch quantity (available_quantity)
     * is recorded as negative surplus by subtracting it from excess_kg.
     */
    public function closeSupply(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_id' => 'required|exists:businesses,id',
            'supply_id' => 'required|exists:supplies,id',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        if ($validator->fails()) {
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();

            return response()->json($response, 400);
        }
        if ($denied = $this->denyUnlessCanAccessBusiness($request)) {
            return $denied;
        }
        if ($denied = $this->denyUnlessCanAccessSupplyForUser($request->supply_id)) {
            return $denied;
        }

        $supply = Supply::find($request->supply_id);
        if ($request->business_id != $supply->business_id) {
            return response()->json([
                'code' => 400,
                'errors' => ['This supply does not belong to this business'],
            ], 400);
        }

        if (! $supply->supplied) {
            return response()->json([
                'code' => 400,
                'errors' => ['Deliver the supply before closing it.'],
            ], 400);
        }

        if ((int) $supply->sold === 1) {
            return response()->json([
                'code' => 400,
                'errors' => ['This supply is already closed.'],
            ], 400);
        }

        if ($supply->unlimited) {
            DB::transaction(function () use ($supply) {
                $totalKgSold = (float) Sale::query()
                    ->where('supply_id', '=', $supply->id)
                    ->sum('kg_quantity');

                $unitCost = (float) $supply->unit_cost;

                $supply->quantity = (string) $totalKgSold;
                $supply->amount = (string) ($totalKgSold * $unitCost);
                $supply->available_quantity = 0;
                $supply->prev_quantity = 0;
                $supply->excess_kg = 0;
                $supply->sold = 1;
                $supply->save();
            });
        } else {
            $remaining = (float) $supply->available_quantity;

            DB::transaction(function () use ($supply, $remaining) {
                $dispenser = Dispenser::find($supply->dispenser_id);
                if ($dispenser && $remaining > 0) {
                    $dispenser->prev_level = $dispenser->current_level;
                    $level = (float) $dispenser->current_level;
                    if ($remaining > $level) {
                        $dispenser->current_level = 0;
                    } else {
                        $dispenser->current_level = $level - $remaining;
                    }
                    $dispenser->save();
                }

                $supply->excess_kg = (int) round((float) $supply->excess_kg - $remaining);
                $supply->available_quantity = 0;
                $supply->prev_quantity = 0;
                $supply->sold = 1;
                $supply->save();
            });
        }

        $supply->refresh();

        return response()->json([
            'code' => 200,
            'message' => 'Supply closed successfully.',
            'data' => $supply,
        ], 200);
    }

    /**
     * Move gas (kg) from a source supply to a new supply on another dispenser in the same location.
     * Pro-rates cost; updates both dispenser levels; marks source as sold when fully drained.
     */
    public function transferSupply(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_id' => 'required|exists:businesses,id',
            'supply_id' => 'required|exists:supplies,id',
            'destination_dispenser_id' => 'required|exists:dispensers,id',
            'quantity' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 400,
                'errors' => $validator->messages()->all(),
            ], 400);
        }

        if ($denied = $this->denyUnlessCanAccessBusiness($request)) {
            return $denied;
        }
        if ($denied = $this->denyUnlessCanAccessSupplyForUser($request->supply_id)) {
            return $denied;
        }

        $tKg = (int) round((float) $request->input('quantity'));
        if ($tKg < 1) {
            return response()->json([
                'code' => 400,
                'errors' => ['Transfer quantity must be at least 1 kg.'],
            ], 400);
        }

        $sourceId = (int) $request->input('supply_id');
        $destDispenserId = (int) $request->input('destination_dispenser_id');

        $result = null;

        try {
        DB::transaction(function () use ($request, $tKg, $sourceId, $destDispenserId, &$result) {
            $source = Supply::whereKey($sourceId)->lockForUpdate()->first();
            if (! $source) {
                throw new \RuntimeException('source_not_found');
            }
            if ((string) $source->business_id !== (string) $request->business_id) {
                throw new \RuntimeException('business_mismatch');
            }
            if (! $source->supplied) {
                throw new \RuntimeException('not_delivered');
            }
            if ((int) $source->sold === 1) {
                throw new \RuntimeException('already_closed');
            }
            if ((int) $source->dispenser_id === $destDispenserId) {
                throw new \RuntimeException('same_dispenser');
            }

            $srcQtyTotal = (float) $source->quantity;
            if ($srcQtyTotal <= 0) {
                throw new \RuntimeException('zero_quantity');
            }

            $available = (int) $source->available_quantity;
            if ($tKg > $available) {
                throw new \RuntimeException('exceeds_available');
            }

            $srcDispId = (int) $source->dispenser_id;
            $orderedIds = $srcDispId < $destDispenserId
                ? [$srcDispId, $destDispenserId]
                : [$destDispenserId, $srcDispId];
            $sourceDisp = null;
            $destDisp = null;
            foreach ($orderedIds as $did) {
                $d = Dispenser::whereKey($did)->lockForUpdate()->first();
                if ($did === $srcDispId) {
                    $sourceDisp = $d;
                }
                if ($did === $destDispenserId) {
                    $destDisp = $d;
                }
            }
            if (! $sourceDisp || ! $destDisp) {
                throw new \RuntimeException('dispenser_not_found');
            }
            if ((string) $sourceDisp->business_id !== (string) $request->business_id
                || (string) $destDisp->business_id !== (string) $request->business_id) {
                throw new \RuntimeException('dispenser_business_mismatch');
            }
            if ((string) $sourceDisp->location_id !== (string) $source->location_id
                || (string) $destDisp->location_id !== (string) $source->location_id) {
                throw new \RuntimeException('location_mismatch');
            }

            $srcAmountTotal = (float) $source->amount;
            $unitCost = $srcQtyTotal > 0 ? $srcAmountTotal / $srcQtyTotal : 0.0;
            $transferAmount = round($unitCost * (float) $tKg, 2);

            $userNote = $request->input('note');
            $baseNote = is_string($userNote) ? trim($userNote) : '';
            $transferNote = 'Transferred from supply #'.$source->id;
            $newNote = $baseNote === '' ? $transferNote : $baseNote.' | '.$transferNote;

            $new = new Supply;
            $new->business_id = $source->business_id;
            $new->location_id = $source->location_id;
            $new->dispenser_id = (string) $destDispenserId;
            $new->quantity = (string) $tKg;
            $new->available_quantity = $tKg;
            $new->amount = (string) $transferAmount;
            $new->supplier_id = $source->supplier_id;
            $new->recieved_by = $source->recieved_by;
            $new->purchased_at = $source->purchased_at;
            $new->delivered_at = $source->delivered_at;
            $new->supplied = true;
            $new->sold = 0;
            $new->excess_kg = 0;
            $new->prev_quantity = 0;
            $new->profit = 0;
            $new->note = $newNote;
            $new->save();

            $source->quantity = (string) round(max(0, $srcQtyTotal - (float) $tKg), 2);
            $source->amount = (string) round(max(0, $srcAmountTotal - $transferAmount), 2);
            $source->available_quantity = $available - $tKg;
            if ((int) $source->available_quantity === 0) {
                $source->sold = 1;
                $source->prev_quantity = 0;
            }
            $source->save();

            $sourceDisp->prev_level = $sourceDisp->current_level;
            $fromLevel = (float) $sourceDisp->current_level;
            if ($tKg > $fromLevel) {
                $sourceDisp->current_level = '0';
            } else {
                $sourceDisp->current_level = (string) ($fromLevel - (float) $tKg);
            }
            $sourceDisp->save();

            $destDisp->prev_level = $destDisp->current_level;
            $destDisp->current_level = (string) ((float) $destDisp->current_level + (float) $tKg);
            $destDisp->save();

            $source->refresh();
            $result = [
                'supply' => $new->fresh(),
                'source' => $source,
            ];
        });
        } catch (\RuntimeException $e) {
            return $this->transferSupplyErrorResponse($e);
        }

        if ($result === null) {
            return response()->json(['code' => 500, 'errors' => ['Transfer failed.']], 500);
        }

        $sourceClosed = (int) $result['source']->sold === 1;
        $message = $sourceClosed
            ? 'Transfer completed. Source supply is closed (fully transferred).'
            : 'Transfer completed.';

        return response()->json([
            'code' => 200,
            'message' => $message,
            'data' => $result,
        ], 200);
    }

    private function transferSupplyErrorResponse(\RuntimeException $e)
    {
        $map = [
            'source_not_found' => ['code' => 404, 'message' => 'Supply not found.'],
            'business_mismatch' => ['code' => 400, 'message' => 'This supply does not belong to this business.'],
            'not_delivered' => ['code' => 400, 'message' => 'Deliver the supply before transferring.'],
            'already_closed' => ['code' => 400, 'message' => 'This supply is already closed.'],
            'same_dispenser' => ['code' => 400, 'message' => 'Choose a different dispenser for the transfer.'],
            'zero_quantity' => ['code' => 400, 'message' => 'Source supply has no quantity to prorate from.'],
            'exceeds_available' => ['code' => 400, 'message' => 'Transfer quantity exceeds available quantity in this supply.'],
            'dispenser_not_found' => ['code' => 400, 'message' => 'Dispenser not found.'],
            'dispenser_business_mismatch' => ['code' => 400, 'message' => 'Dispenser does not belong to this business.'],
            'location_mismatch' => ['code' => 400, 'message' => 'Destination must be a dispenser in the same location as the supply.'],
        ];

        $key = $e->getMessage();
        $meta = $map[$key] ?? ['code' => 400, 'message' => 'Transfer failed.'];

        return response()->json([
            'code' => $meta['code'],
            'errors' => [$meta['message']],
        ], $meta['code']);
    }
}
