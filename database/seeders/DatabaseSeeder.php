<?php

namespace Database\Seeders;

use App\Models\Sale;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        Sale::create([
            'business_id' => '4',
            'location_id' => '2',
            'dispenser_id' => '2',
            'opening_sales' => '2',
            'closing_sales' => '2',
            'closing_kg' => '2',
            'opening_kg' => '2',
            'price_id' => '2',
            'kg_quantity' => '2',
            'amount' => '2',
            'sales_date' => '2',
            'uploaded_by' => now()
        ]);
    }
}
