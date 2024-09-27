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
        Schema::create('operation__costs', function (Blueprint $table) {
            $table->id();
            $table->string('business_id');
            $table->string('location_id');
            $table->string('title');
            $table->string('amount');
            $table->string('description');
            $table->string('paid_at');
            $table->string('paidby_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation__costs');
    }
};
