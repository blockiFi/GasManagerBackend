<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => '4',
            'loaction_id' => '2',
            'dispenser_id' => '2',
            'opening_sales' => '2',
            'closing_sales' => '2',
            'closing_kg' => '2',
            'price_id' => '2',
            'kg_quantity' => '2',
            'amount' => '2',
            'sales_date' => '2'
        ];
    }

 
}
