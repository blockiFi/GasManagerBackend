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
            $table->string('quatity');
            $table->string('amount');
            $table->string('supplier_id');
            $table->string('recieved_by');
            $table->string('note')->nullable();
            $table->string('purchased_at');
            $table->string('delivered_at');
            $table->boolean('supplied')->default(false);
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
