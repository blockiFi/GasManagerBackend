<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Supply;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SalesGroupedAnalyticsService
{
    /**
     * @return array{success: true, data: array<int, mixed>}|array{success: false, code: int, errors: list<string>}
     */
    public function buildGroupedSales(Request $request): array
    {
        $useAll = filter_var($request->input('all'), FILTER_VALIDATE_BOOLEAN);

        if ($useAll) {
            $sales = Sale::where('business_id', '=', $request->business_id)->get();
            $supplies = Supply::where('business_id', '=', $request->business_id)->get();
        } else {
            $sales = Sale::where('location_id', '=', $request->location_id)->get();
            $supplies = Supply::where('location_id', '=', $request->location_id)->get();
        }

        $groupParameter = $request->input('groupParameter');

        if ($groupParameter === 'monthly') {
            $grouped = $sales->groupBy(function ($sale) {
                return Carbon::parse($sale->sales_date)->format('Y-m');
            });
            $groupedSupplies = $supplies->groupBy(function ($supply) {
                $lastSale = $supply->sales()->orderBy('sales_date', 'desc')->first();
                $date = $lastSale ? $lastSale->sales_date : $supply->updated_at;

                return Carbon::parse($date)->format('Y-m');
            });
        } elseif ($groupParameter === 'quarterly') {
            $grouped = $sales->groupBy(function ($sale) {
                $date = Carbon::parse($sale->sales_date);

                return 'Q'.$date->quarter.' '.$date->format('Y');
            });
            $groupedSupplies = $supplies->groupBy(function ($supply) {
                $lastSale = $supply->sales()->orderBy('sales_date', 'desc')->first();
                $date = $lastSale ? $lastSale->sales_date : $supply->updated_at;
                $date = Carbon::parse($date);

                return 'Q'.$date->quarter.' '.$date->format('Y');
            });
        } elseif ($groupParameter === 'yearly') {
            $grouped = $sales->groupBy(function ($sale) {
                return Carbon::parse($sale->sales_date)->format('Y');
            });
            $groupedSupplies = $supplies->groupBy(function ($supply) {
                $lastSale = $supply->sales()->orderBy('sales_date', 'desc')->first();
                $date = $lastSale ? $lastSale->sales_date : $supply->updated_at;

                return Carbon::parse($date)->format('Y');
            });
        } else {
            $grouped = $sales->groupBy(function ($sale) {
                return Carbon::parse($sale->sales_date)->startOfWeek()->format('Y-m-d');
            });
            $groupedSupplies = $supplies->filter(function ($supply) use ($grouped) {
                $lastSale = $supply->sales()->orderBy('sales_date', 'desc')->first();
                if (! $lastSale) {
                    return false;
                }
                $saleWeek = Carbon::parse($lastSale->sales_date)->startOfWeek()->format('Y-m-d');
                $weeksInGrouping = collect($grouped)->keys();

                return $weeksInGrouping->contains($saleWeek);
            })->groupBy(function ($supply) {
                $lastSale = $supply->sales()->orderBy('sales_date', 'desc')->first();

                return Carbon::parse($lastSale->sales_date)->startOfWeek()->format('Y-m-d');
            });
        }

        $salesData = [];

        foreach ($grouped as $group => $salesIngroup) {
            $data = [];
            $data['group'] = $group;
            $totalSalesAmount = 0;
            $totalSalesKg = 0;
            $totalProfit = 0;
            $totalExcessKg = 0;
            $ExcessKgProfit = 0;
            $supplyRows = [];

            foreach ($salesIngroup as $sale) {
                $supply = $sale->supply;
                if (! isset($supply)) {
                    return [
                        'success' => false,
                        'code' => 422,
                        'errors' => ['A sale in this period is missing supply linkage.'],
                    ];
                }
                $totalSalesAmount += $sale->amount;
                $totalSalesKg += $sale->kg_quantity;
                $qty = (float) $supply->quantity;
                $unitCost = $qty > 0 ? ($supply->amount / $qty) : 0;
                $profit = ($sale->amount) - ($unitCost * $sale->kg_quantity);
                $totalProfit += $profit;
            }

            if (isset($groupedSupplies[$group])) {
                foreach ($groupedSupplies[$group] as $supply) {
                    $supplyRows[] = $supply->load('LastSale')->toArray();
                    $totalExcessKg += $supply->excess_kg;
                    $sq = (float) $supply->quantity;
                    $ExcessKgProfit += $sq > 0 ? ($supply->amount / $sq * $supply->excess_kg) : 0;
                }
            }

            $data['totalSalesAmount'] = $totalSalesAmount;
            $data['totalSalesKg'] = $totalSalesKg;
            $data['profit'] = $totalProfit;
            $data['totalProfit'] = $totalProfit + $ExcessKgProfit;
            $data['totalExcessKg'] = $totalExcessKg;
            $data['ExcessKgProfit'] = $ExcessKgProfit;
            $data['supplies'] = $supplyRows;

            $salesData[] = $data;
        }

        return ['success' => true, 'data' => $salesData];
    }
}
