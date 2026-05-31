<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Dispenser;
use App\Models\Location;
use App\Models\Price;
use App\Models\Sale;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddSaleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Business, 1: Location, 2: Dispenser, 3: Supply, 4: Price, 5: User}
     */
    private function seedTenantForAddSale(): array
    {
        $owner = User::factory()->create();

        $business = new Business;
        $business->name = 'Test Biz';
        $business->address = 'Addr';
        $business->owner_id = (string) $owner->id;
        $business->is_active = true;
        $business->save();

        $location = new Location;
        $location->name = 'Loc';
        $location->address = 'LAddr';
        $location->business_id = (string) $business->id;
        $location->manager_id = (string) $owner->id;
        $location->save();

        $dispenser = new Dispenser;
        $dispenser->business_id = (string) $business->id;
        $dispenser->location_id = (string) $location->id;
        $dispenser->name = 'D1';
        $dispenser->capacity = '1000';
        $dispenser->empty_sale = 'false';
        $dispenser->current_level = '40';
        $dispenser->prev_level = '50';
        $dispenser->save();

        $supply = new Supply;
        $supply->business_id = (string) $business->id;
        $supply->location_id = (string) $location->id;
        $supply->dispenser_id = (string) $dispenser->id;
        $supply->quantity = '100';
        $supply->amount = '10000';
        $supply->supplier_id = '1';
        $supply->recieved_by = (string) $owner->id;
        $supply->purchased_at = '2024-01-01';
        $supply->delivered_at = '2024-01-02';
        $supply->available_quantity = 100;
        $supply->prev_quantity = 100;
        $supply->sold = 0;
        $supply->excess_kg = 0;
        $supply->profit = 0;
        $supply->save();

        $price = new Price;
        $price->business_id = (string) $business->id;
        $price->location_id = (string) $location->id;
        $price->price = '50';
        $price->active = 'true';
        $price->set_by = (string) $owner->id;
        $price->save();

        return [$business, $location, $dispenser, $supply, $price, $owner];
    }

    public function test_owner_can_add_first_sale_and_updates_dispenser_and_supply(): void
    {
        [$business, $location, $dispenser, $supply, , $owner] = $this->seedTenantForAddSale();

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/business/location/add_sales', [
            'business_id' => (string) $business->id,
            'location_id' => (string) $location->id,
            'dispenser_id' => (string) $dispenser->id,
            'opening_sales' => '0',
            'closing_sales' => '500',
            'opening_kg' => '0',
            'closing_kg' => '10',
            'sales_date' => '2026-04-20',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Sales Added Successfully!!!');

        $this->assertDatabaseHas('sales', [
            'business_id' => (string) $business->id,
            'location_id' => (string) $location->id,
            'dispenser_id' => (string) $dispenser->id,
            'amount' => '500',
            'kg_quantity' => '10',
        ]);

        $dispenser->refresh();
        $this->assertSame('40', $dispenser->prev_level);
        $this->assertSame('30', $dispenser->current_level);

        $supply->refresh();
        $this->assertSame(90, (int) $supply->available_quantity);
    }

    public function test_add_sale_returns_422_when_no_supply_exists(): void
    {
        $owner = User::factory()->create();

        $business = new Business;
        $business->name = 'Test Biz';
        $business->address = 'Addr';
        $business->owner_id = (string) $owner->id;
        $business->is_active = true;
        $business->save();

        $location = new Location;
        $location->name = 'Loc';
        $location->address = 'LAddr';
        $location->business_id = (string) $business->id;
        $location->manager_id = (string) $owner->id;
        $location->save();

        $dispenser = new Dispenser;
        $dispenser->business_id = (string) $business->id;
        $dispenser->location_id = (string) $location->id;
        $dispenser->name = 'D1';
        $dispenser->capacity = '1000';
        $dispenser->empty_sale = 'false';
        $dispenser->current_level = '40';
        $dispenser->prev_level = '50';
        $dispenser->save();

        $price = new Price;
        $price->business_id = (string) $business->id;
        $price->location_id = (string) $location->id;
        $price->price = '50';
        $price->active = 'true';
        $price->set_by = (string) $owner->id;
        $price->save();

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/business/location/add_sales', [
            'business_id' => (string) $business->id,
            'location_id' => (string) $location->id,
            'dispenser_id' => (string) $dispenser->id,
            'opening_sales' => '0',
            'closing_sales' => '500',
            'opening_kg' => '0',
            'closing_kg' => '10',
            'sales_date' => '2026-04-20',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
        $this->assertStringContainsString('supply', strtolower($response->json('errors.0')));

        $this->assertSame(0, Sale::query()->count());
    }

    /**
     * When closing_sales < opening_sales by more than one METER_ROLLOVER_MODULUS (1e6),
     * amount must still be positive (multiple full meter wraps).
     */
    public function test_first_sale_with_multiple_cash_meter_wraps(): void
    {
        [$business, $location, $dispenser, $supply, , $owner] = $this->seedTenantForAddSale();

        $price = Price::query()
            ->where('location_id', $location->id)
            ->where('business_id', $business->id)
            ->first();
        $price->price = '100';
        $price->save();

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/business/location/add_sales', [
            'business_id' => (string) $business->id,
            'location_id' => (string) $location->id,
            'dispenser_id' => (string) $dispenser->id,
            'opening_sales' => '1000000',
            'closing_sales' => '2000',
            'opening_kg' => '0',
            'closing_kg' => '20',
            'sales_date' => '2026-04-21',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('sales', [
            'dispenser_id' => (string) $dispenser->id,
            'amount' => '2000',
            'kg_quantity' => '20',
        ]);
    }

    public function test_rollover_infers_10_million_modulus_from_reading_magnitude(): void
    {
        [$business, $location, $dispenser, $supply, , $owner] = $this->seedTenantForAddSale();

        $price = Price::query()
            ->where('location_id', $location->id)
            ->where('business_id', $business->id)
            ->first();
        $price->price = '190000';
        $price->save();

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/business/location/add_sales', [
            'business_id' => (string) $business->id,
            'location_id' => (string) $location->id,
            'dispenser_id' => (string) $dispenser->id,
            'opening_sales' => '9100000',
            'closing_sales' => '1000000',
            'opening_kg' => '0',
            'closing_kg' => '10',
            'sales_date' => '2026-04-22',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('sales', [
            'dispenser_id' => (string) $dispenser->id,
            'amount' => '1900000',
            'kg_quantity' => '10',
        ]);
    }
}
