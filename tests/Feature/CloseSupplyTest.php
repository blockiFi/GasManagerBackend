<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Dispenser;
use App\Models\Location;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CloseSupplyTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Business, 1: Location, 2: Dispenser, 3: Supply, 4: User} */
    private function seedDeliveredOpenSupply(User $owner): array
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
        $dispenser->current_level = '100';
        $dispenser->prev_level = '100';
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
        $supply->supplied = true;
        $supply->available_quantity = 25;
        $supply->prev_quantity = 25;
        $supply->sold = 0;
        $supply->excess_kg = 10;
        $supply->profit = 100;
        $supply->save();

        return [$business, $location, $dispenser, $supply, $owner];
    }

    public function test_close_supply_records_remaining_as_negative_surplus(): void
    {
        $owner = User::factory()->create();
        [$business, $location, $dispenser, $supply] = $this->seedDeliveredOpenSupply($owner);

        Passport::actingAs($owner);

        $response = $this->postJson('/api/business/supply/close_business_supply', [
            'business_id' => (string) $business->id,
            'supply_id' => $supply->id,
            'location_id' => (string) $location->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 200);

        $supply->refresh();
        $this->assertSame(1, (int) $supply->sold);
        $this->assertSame(0, (int) $supply->available_quantity);
        $this->assertSame(0, (int) $supply->prev_quantity);
        $this->assertSame(-15, (int) $supply->excess_kg);
        $this->assertSame(100, (int) (float) $supply->profit);

        $dispenser->refresh();
        $this->assertEquals(100.0, (float) $dispenser->prev_level);
        $this->assertEquals(75.0, (float) $dispenser->current_level);
    }

    public function test_close_supply_without_remaining_only_marks_sold(): void
    {
        $owner = User::factory()->create();
        [$business, $location, $dispenser, $supply] = $this->seedDeliveredOpenSupply($owner);
        $supply->available_quantity = 0;
        $supply->excess_kg = 5;
        $supply->save();

        Passport::actingAs($owner);

        $this->postJson('/api/business/supply/close_business_supply', [
            'business_id' => (string) $business->id,
            'supply_id' => $supply->id,
            'location_id' => (string) $location->id,
        ])->assertOk();

        $supply->refresh();
        $this->assertSame(1, (int) $supply->sold);
        $this->assertSame(5, (int) $supply->excess_kg);

        $dispenser->refresh();
        $this->assertEquals(100.0, (float) $dispenser->current_level);
        $this->assertEquals(100.0, (float) $dispenser->prev_level);
    }

    public function test_close_supply_clamps_dispenser_when_remaining_exceeds_level(): void
    {
        $owner = User::factory()->create();
        [$business, $location, $dispenser, $supply] = $this->seedDeliveredOpenSupply($owner);
        $dispenser->current_level = '10';
        $dispenser->prev_level = '10';
        $dispenser->save();
        $supply->available_quantity = 25;
        $supply->save();

        Passport::actingAs($owner);

        $this->postJson('/api/business/supply/close_business_supply', [
            'business_id' => (string) $business->id,
            'supply_id' => $supply->id,
            'location_id' => (string) $location->id,
        ])->assertOk();

        $dispenser->refresh();
        $this->assertEquals(10.0, (float) $dispenser->prev_level);
        $this->assertEquals(0.0, (float) $dispenser->current_level);
    }

    public function test_close_fails_if_not_delivered(): void
    {
        $owner = User::factory()->create();
        [$business, $location, , $supply] = $this->seedDeliveredOpenSupply($owner);
        $supply->supplied = false;
        $supply->save();

        Passport::actingAs($owner);

        $this->postJson('/api/business/supply/close_business_supply', [
            'business_id' => (string) $business->id,
            'supply_id' => $supply->id,
            'location_id' => (string) $location->id,
        ])->assertStatus(400)
            ->assertJsonPath('errors.0', 'Deliver the supply before closing it.');
    }

    public function test_close_fails_if_already_closed(): void
    {
        $owner = User::factory()->create();
        [$business, $location, , $supply] = $this->seedDeliveredOpenSupply($owner);
        $supply->sold = 1;
        $supply->save();

        Passport::actingAs($owner);

        $this->postJson('/api/business/supply/close_business_supply', [
            'business_id' => (string) $business->id,
            'supply_id' => $supply->id,
            'location_id' => (string) $location->id,
        ])->assertStatus(400)
            ->assertJsonPath('errors.0', 'This supply is already closed.');
    }
}
