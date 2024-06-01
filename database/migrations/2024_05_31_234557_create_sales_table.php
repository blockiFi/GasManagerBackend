<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('business_id');
            $table->string('location_id');
            $table->string('dispenser_id');
            $table->string('opening_sales');
            $table->string('closing_sales');
            $table->string('opening_kg');
            $table->string('closing_kg');
            $table->string('price');
            $table->string('kg_quantity');
            $table->string('amount');
            $table->string('sales_date');
            $table->string('uploaded_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
