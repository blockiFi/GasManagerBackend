<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TenantAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyze_image_requires_authentication(): void
    {
        $this->postJson('/api/analyze-image', [])
            ->assertStatus(401);
    }

    public function test_sales_breakdown_forbidden_for_unrelated_user(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $businessA = new Business;
        $businessA->name = 'Biz A';
        $businessA->address = 'Addr';
        $businessA->owner_id = (string) $ownerA->id;
        $businessA->is_active = true;
        $businessA->save();
        $businessB = new Business;
        $businessB->name = 'Biz B';
        $businessB->address = 'Addr';
        $businessB->owner_id = (string) $ownerB->id;
        $businessB->is_active = true;
        $businessB->save();

        Passport::actingAs($ownerA);

        $this->postJson('/api/get_business/get_sales_data', [
            'business_id' => $businessB->id,
        ])->assertStatus(403);
    }

    public function test_owner_can_access_own_sales_breakdown(): void
    {
        $owner = User::factory()->create();
        $business = new Business;
        $business->name = 'Mine';
        $business->address = 'Addr';
        $business->owner_id = (string) $owner->id;
        $business->is_active = true;
        $business->save();

        Passport::actingAs($owner);

        $this->postJson('/api/get_business/get_sales_data', [
            'business_id' => $business->id,
        ])->assertOk();
    }
}
