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
        Schema::create('supplies', function (Blueprint $table) {
            $table->id();
            $table->string('business_id');
            $table->string('location_id');
            $table->string('dispenser_id');
            $table->string('quantity');
            $table->string('amount');
            $table->string('supplier_id');
            $table->string('recieved_by');
            $table->string('note')->nullable();
            $table->string('purchased_at');
            $table->string('delivered_at');
            $table->boolean('supplied')->default(false);
            $table->interger('available_quantity')->default(0);
            $table->interger('prev_quantity')->default(0);
            $table->interger('sold')->default(0);
            $table->interger('excess_kg')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplies');
    }
};
