<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Dispenser;
use App\Models\Location;
use App\Models\Sale;
use App\Models\Sale_Reciept;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReverseLatestSaleTest extends TestCase
{
    use RefreshDatabase;

    private function seedOwnerBusinessLocationDispenserSupplyPrice(User $owner): array
    {
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
        $supply->available_quantity = 70;
        $supply->prev_quantity = 80;
        $supply->sold = 0;
        $supply->excess_kg = 0;
        $supply->profit = 0;
        $supply->save();

        return [$business, $location, $dispenser, $supply];
    }

    public function test_owner_can_reverse_latest_sale_and_restores_dispenser_and_supply(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        [$business, $location, $dispenser, $supply] = $this->seedOwnerBusinessLocationDispenserSupplyPrice($owner);

        $sale = new Sale;
        $sale->business_id = (string) $business->id;
        $sale->location_id = (string) $location->id;
        $sale->dispenser_id = (string) $dispenser->id;
        $sale->supply_id = $supply->id;
        $sale->opening_sales = '0';
        $sale->closing_sales = '100';
        $sale->opening_kg = '0';
        $sale->closing_kg = '10';
        $sale->price_id = '1';
        $sale->kg_quantity = '10';
        $sale->amount = '5000';
        $sale->status = 'pending';
        $sale->sales_date = '2026-04-17';
        $sale->uploaded_by = (string) $owner->id;
        $sale->save();

        $receipt = new Sale_Reciept;
        $receipt->sales_id = (string) $sale->id;
        $receipt->image_path = 'receipts/test.jpg';
        $receipt->save();
        Storage::disk('public')->put('receipts/test.jpg', 'x');

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/business/sales/reverse_latest_sale', [
            'business_id' => (string) $business->id,
            'location_id' => (string) $location->id,
            'dispenser_id' => (string) $dispenser->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 200);

        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertDatabaseMissing('sale__reciepts', ['sales_id' => (string) $sale->id]);

        $dispenser->refresh();
        $this->assertEquals(50.0, (float) $dispenser->current_level);
        $this->assertEquals(50.0, (float) $dispenser->prev_level);

        $supply->refresh();
        $this->assertSame(80, (int) $supply->available_quantity);
        $this->assertSame(80, (int) $supply->prev_quantity);
        $this->assertSame(0, (int) $supply->sold);
    }

    public function test_reverse_forbidden_for_unrelated_user(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        [$business, $location, $dispenser, $supply] = $this->seedOwnerBusinessLocationDispenserSupplyPrice($owner);

        $sale = new Sale;
        $sale->business_id = (string) $business->id;
        $sale->location_id = (string) $location->id;
        $sale->dispenser_id = (string) $dispenser->id;
        $sale->supply_id = $supply->id;
        $sale->opening_sales = '0';
        $sale->closing_sales = '100';
        $sale->opening_kg = '0';
        $sale->closing_kg = '10';
        $sale->price_id = '1';
        $sale->kg_quantity = '10';
        $sale->amount = '5000';
        $sale->status = 'pending';
        $sale->sales_date = '2026-04-17';
        $sale->uploaded_by = (string) $owner->id;
        $sale->save();

        Sanctum::actingAs($stranger);

        $this->postJson('/api/business/sales/reverse_latest_sale', [
            'business_id' => (string) $business->id,
            'location_id' => (string) $location->id,
            'dispenser_id' => (string) $dispenser->id,
        ])->assertStatus(403);

        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
    }

    public function test_reverse_returns_404_when_no_sales(): void
    {
        $owner = User::factory()->create();
        [$business, $location, $dispenser] = $this->seedOwnerBusinessLocationDispenserSupplyPrice($owner);

        Sanctum::actingAs($owner);

        $this->postJson('/api/business/sales/reverse_latest_sale', [
            'business_id' => (string) $business->id,
            'location_id' => (string) $location->id,
            'dispenser_id' => (string) $dispenser->id,
        ])->assertStatus(404);
    }

    public function test_reverse_returns_409_when_sale_spilled_past_supply_snapshot(): void
    {
        $owner = User::factory()->create();
        [$business, $location, $dispenser, $supply] = $this->seedOwnerBusinessLocationDispenserSupplyPrice($owner);

        $supply->sold = 1;
        $supply->available_quantity = 0;
        $supply->prev_quantity = 5;
        $supply->excess_kg = 0;
        $supply->profit = 0;
        $supply->save();

        $older = new Sale;
        $older->business_id = (string) $business->id;
        $older->location_id = (string) $location->id;
        $older->dispenser_id = (string) $dispenser->id;
        $older->supply_id = $supply->id;
        $older->opening_sales = '0';
        $older->closing_sales = '50';
        $older->opening_kg = '0';
        $older->closing_kg = '50';
        $older->price_id = '1';
        $older->kg_quantity = '50';
        $older->amount = '2500';
        $older->status = 'pending';
        $older->sales_date = '2026-04-16';
        $older->uploaded_by = (string) $owner->id;
        $older->save();

        $latest = new Sale;
        $latest->business_id = (string) $business->id;
        $latest->location_id = (string) $location->id;
        $latest->dispenser_id = (string) $dispenser->id;
        $latest->supply_id = $supply->id;
        $latest->opening_sales = '50';
        $latest->closing_sales = '150';
        $latest->opening_kg = '50';
        $latest->closing_kg = '60';
        $latest->price_id = '1';
        $latest->kg_quantity = '10';
        $latest->amount = '5000';
        $latest->status = 'pending';
        $latest->sales_date = '2026-04-17';
        $latest->uploaded_by = (string) $owner->id;
        $latest->save();

        Sanctum::actingAs($owner);

        $this->postJson('/api/business/sales/reverse_latest_sale', [
            'business_id' => (string) $business->id,
            'location_id' => (string) $location->id,
            'dispenser_id' => (string) $dispenser->id,
        ])->assertStatus(409);

        $this->assertDatabaseHas('sales', ['id' => $latest->id]);
        $supply->refresh();
        $this->assertSame(1, (int) $supply->sold);
        $this->assertSame(0, (int) $supply->available_quantity);
        $this->assertSame(5, (int) $supply->prev_quantity);
    }
}
