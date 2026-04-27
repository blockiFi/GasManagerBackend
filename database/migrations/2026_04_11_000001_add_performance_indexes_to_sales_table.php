<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['business_id', 'location_id', 'sales_date'], 'sales_business_location_date_idx');
            $table->index(['location_id', 'dispenser_id', 'sales_date'], 'sales_location_dispenser_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_business_location_date_idx');
            $table->dropIndex('sales_location_dispenser_date_idx');
        });
    }
};
