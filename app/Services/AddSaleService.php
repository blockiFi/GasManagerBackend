<?php

namespace App\Services;

use App\Exceptions\AddSaleValidationFailure;
use App\Models\Business_Setting;
use App\Models\Dispenser;
use App\Models\Price;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Supply;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AddSaleService
{
    /**
     * Cash meter wrap sizes. Inferred from opening/closing magnitude when closing < opening (rollover).
     *
     * @var list<int>
     */
    public const CASH_METER_MODULI = [1_000_000, 10_000_000, 100_000_000];

    /** Unused for math when closing >= opening; placeholder only. */
    public const DEFAULT_CASH_METER_MODULUS = 1_000_000;

    /**
     * @param  array{
     *     business_id: string,
     *     location_id: string,
     *     dispenser_id: string,
     *     opening_sales: mixed,
     *     closing_sales: mixed,
     *     opening_kg: mixed,
     *     closing_kg: mixed,
     *     sales_date: string,
     *     user_id: int|string
     * }  $payload
     */
    public function add(array $payload): Sale
    {
        return DB::transaction(function () use ($payload) {
            $businessId = (string) $payload['business_id'];
            $locationId = (string) $payload['location_id'];
            $dispenserId = (string) $payload['dispenser_id'];
            $userId = (string) $payload['user_id'];

            $dispenser = Dispenser::query()
                ->where('id', $dispenserId)
                ->where('business_id', $businessId)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->first();

            if (! $dispenser) {
                $this->fail(422, ['Dispenser not found or does not belong to this location.']);
            }

            $supply = Supply::query()
                ->where('business_id', $businessId)
                ->where('location_id', $locationId)
                ->where('dispenser_id', $dispenserId)
                ->where('sold', '0')
                ->lockForUpdate()
                ->first();

            if (! $supply) {
                $supply = Supply::query()
                    ->where('business_id', $businessId)
                    ->where('location_id', $locationId)
                    ->where('dispenser_id', $dispenserId)
                    ->lockForUpdate()
                    ->latest()
                    ->first();
            }

            if (! $supply) {
                $this->fail(422, ['No supply batch found for this dispenser. Record a supply delivery before adding sales.']);
            }

            $salesDate = Carbon::parse($payload['sales_date']);

            $price = Price::query()
                ->where('business_id', $businessId)
                ->where('location_id', $locationId)
                ->where('active', 'true')
                ->first();

            if (! $price) {
                $this->fail(400, ['No Price Available Please set sale Price']);
            }

            $prevSale = $this->previousSaleForDispenser($businessId, $locationId, $dispenserId, $salesDate);

            $closingSales = (float) $payload['closing_sales'];
            $closingKg = (float) $payload['closing_kg'];
            $openingSalesReq = (float) $payload['opening_sales'];
            $openingKgReq = (float) $payload['opening_kg'];

            if ($prevSale) {
                $openingSales = (float) $prevSale->closing_sales;
                $openingKg = (float) $prevSale->closing_kg;
                $kgQuantity = $closingKg - $openingKg;
                $modulus = $this->inferCashMeterModulusForAmount($closingSales, $openingSales);
                $amount = $this->meterDeltaAmount($closingSales, $openingSales, $modulus);

                $this->assertPositiveDeltas($kgQuantity, $amount);
                $this->assertPriceWithinThreshold(
                    $amount / $kgQuantity,
                    $price,
                    $businessId
                );

                $sale = new Sale;
                $sale->business_id = $businessId;
                $sale->location_id = $locationId;
                $sale->dispenser_id = $dispenserId;
                $sale->opening_sales = (string) $prevSale->closing_sales;
                $sale->closing_sales = (string) $payload['closing_sales'];
                $sale->opening_kg = (string) $prevSale->closing_kg;
                $sale->closing_kg = (string) $payload['closing_kg'];
                $sale->amount = (string) $amount;
                $sale->status = 'pending';
                $sale->kg_quantity = (string) $kgQuantity;
            } else {
                $openingSales = $openingSalesReq;
                $openingKg = $openingKgReq;
                $kgQuantity = $closingKg - $openingKg;
                $modulus = $this->inferCashMeterModulusForAmount($closingSales, $openingSales);
                $amount = $this->meterDeltaAmount($closingSales, $openingSales, $modulus);

                $this->assertPositiveDeltas($kgQuantity, $amount);
                $this->assertPriceWithinThreshold(
                    $amount / $kgQuantity,
                    $price,
                    $businessId
                );

                $sale = new Sale;
                $sale->business_id = $businessId;
                $sale->location_id = $locationId;
                $sale->dispenser_id = $dispenserId;
                $sale->opening_sales = (string) $payload['opening_sales'];
                $sale->closing_sales = (string) $payload['closing_sales'];
                $sale->opening_kg = (string) $payload['opening_kg'];
                $sale->closing_kg = (string) $payload['closing_kg'];
                $sale->amount = (string) $amount;
                $sale->status = 'pending';
                $sale->kg_quantity = (string) $kgQuantity;
            }

            $sale->sales_date = $salesDate;
            $sale->uploaded_by = $userId;
            $sale->price_id = (string) $price->id;
            $sale->supply_id = $supply->id;
            $sale->status = '';
            $sale->save();

            $this->applyDispenserLevelUpdate($dispenser, (float) $sale->kg_quantity);
            $this->applySupplyUpdates(
                $supply,
                $dispenser,
                $sale,
                $businessId,
                $locationId,
                $dispenserId
            );

            $dispenser->save();

            return $sale->fresh();
        });
    }

    /**
     * @return never
     */
    private function fail(int $status, array $errors): void
    {
        throw new AddSaleValidationFailure($status, $errors);
    }

    private function previousSaleForDispenser(
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

    /**
     * Sales amount delta for a cash counter that wraps every $modulus units (1e6, 1e7, or 1e8).
     * When closing < opening, apply the minimum number of full wraps so the delta is non-negative.
     */
    private function meterDeltaAmount(float $closing, float $opening, int $modulus): float
    {
        if ($closing >= $opening) {
            return $closing - $opening;
        }

        $shortfall = $opening - $closing;
        $wraps = (int) ceil($shortfall / $modulus);

        return $closing - $opening + ($wraps * $modulus);
    }

    /**
     * When closing < opening (rollover), infer wrap size from reading magnitude (opening vs closing).
     * max(opening, closing) in (0 … 1_000_000] → 1_000_000 wrap (e.g. 999_000 down to 500);
     * up to 99_999_999 → 10_000_000 wrap; otherwise → 100_000_000 wrap.
     */
    private function inferCashMeterModulusForAmount(float $closingSales, float $openingSales): int
    {
        if ($closingSales >= $openingSales) {
            return self::DEFAULT_CASH_METER_MODULUS;
        }

        $peak = max($openingSales, $closingSales);
        if ($peak <= 1_000_000) {
            return 1_000_000;
        }
        if ($peak <= 99_999_999) {
            return 10_000_000;
        }

        return 100_000_000;
    }

    private function resolveSalesPriceThreshold(string $businessId): float
    {
        $threshold = 0.0;
        $setting = Setting::query()->where('name', 'sales_price_threshold')->first();
        if ($setting) {
            $threshold = (float) $setting->default;
            $businessSetting = Business_Setting::query()
                ->where('business_id', $businessId)
                ->where('setting_id', $setting->id)
                ->first();
            if ($businessSetting) {
                $threshold = (float) $businessSetting->value;
            }
        }

        return $threshold;
    }

    private function salesPriceIsWithinListThreshold(float $salesPrice, Price $price, string $businessId): bool
    {
        $listPrice = (float) $price->price;

        if ($salesPrice == $listPrice) {
            return true;
        }

        $threshold = $this->resolveSalesPriceThreshold($businessId);
        $priceDifference = abs($salesPrice - $listPrice);

        if ($salesPrice > $listPrice && $priceDifference > $threshold) {
            return false;
        }

        if ($salesPrice < $listPrice && $listPrice - $salesPrice > $threshold) {
            return false;
        }

        return true;
    }

    private function assertPositiveDeltas(float $kgQuantity, float $amount): void
    {
        if ($kgQuantity < 0) {
            $this->fail(400, ['Sales Kg is negative, meaning the data uploaded is not valid.']);
        }
        if ($amount < 0) {
            $this->fail(400, ['Sales amount is negative, meaning the data uploaded is not valid.']);
        }
        if ($kgQuantity == 0.0) {
            $this->fail(400, ['Sales Kg is 0, meaning no sales made']);
        }
        if ($amount == 0.0) {
            $this->fail(400, ['Sales Amount is 0, meaning no sales made']);
        }
    }

    private function assertPriceWithinThreshold(float $salesPrice, Price $price, string $businessId): void
    {
        if ($this->salesPriceIsWithinListThreshold($salesPrice, $price, $businessId)) {
            return;
        }

        $listPrice = (float) $price->price;
        $threshold = $this->resolveSalesPriceThreshold($businessId);
        $priceDifference = abs($salesPrice - $listPrice);

        if ($salesPrice > $listPrice && $priceDifference > $threshold) {
            $this->fail(400, [
                'Sales Price is higher than the current price',
                "{$listPrice} is the current price",
                "{$salesPrice} is the sales price",
                "{$priceDifference} is the difference",
                "{$threshold} is the price threshold",
            ]);
        }

        if ($salesPrice < $listPrice && $listPrice - $salesPrice > $threshold) {
            $this->fail(400, [
                'Sales Price is lower than the current price',
                "{$listPrice} is the current price",
                "{$salesPrice} is the sales price",
                "{$threshold} is the price threshold",
                "{$priceDifference} is the difference",
            ]);
        }
    }

    private function applyDispenserLevelUpdate(Dispenser $dispenser, float $kgQuantity): void
    {
        $dispenser->prev_level = $dispenser->current_level;
        $current = (float) $dispenser->current_level;

        if ($kgQuantity > $current) {
            $dispenser->current_level = '0';
        } else {
            $dispenser->current_level = (string) ($current - $kgQuantity);
        }
    }

    private function applySupplyUpdates(
        Supply $supply,
        Dispenser $dispenser,
        Sale $sale,
        string $businessId,
        string $locationId,
        string $dispenserId
    ): void {
        $kgQty = (float) $sale->kg_quantity;
        $saleAmount = (float) $sale->amount;

        if ($supply->sold == 1) {
            $supplyCostPerKg = (float) $supply->amount / max(1.0, (float) $supply->quantity);
            $_profit = $saleAmount - ($kgQty * $supplyCostPerKg);
            $supply->profit = (float) ($supply->profit ?? 0) + $_profit;
            $supply->excess_kg = (int) ((float) ($supply->excess_kg ?? 0) + $kgQty);
            $supply->save();

            return;
        }

        $available = (float) $supply->available_quantity;

        if ($kgQty > $available) {
            $remainingKg = $kgQty - $available;

            if ($dispenser->empty_sale === 'true') {
                $supply->available_quantity = 0;
                $supply->sold = 1;
                $salesRows = Sale::query()->where('supply_id', '=', $supply->id)->get();
                $totalSales = 0.0;
                foreach ($salesRows as $row) {
                    $totalSales += (float) $row->amount;
                }
                $supply->profit = $totalSales - (float) $supply->amount;
                $supply->excess_kg = $remainingKg;
                $supply->save();
            } else {
                $supply->prev_quantity = $supply->available_quantity;
                $supply->available_quantity = 0;
                $supply->sold = 1;
                $supply->save();

                $newSupply = Supply::query()
                    ->where('business_id', $businessId)
                    ->where('location_id', $locationId)
                    ->where('dispenser_id', $dispenserId)
                    ->where('sold', '0')
                    ->lockForUpdate()
                    ->first();

                if ($newSupply) {
                    $newSupply->available_quantity = (float) $newSupply->available_quantity - $remainingKg;
                    $newSupply->save();
                }
            }
        } else {
            $supply->prev_quantity = $supply->available_quantity;
            $supply->available_quantity = $available - $kgQty;
            $supply->save();
        }
    }
}
