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
        Schema::create('dispensers', function (Blueprint $table) {
            $table->id();
            $table->string('business_id');
            $table->string('location_id');
            $table->string('name');
            $table->string('capacity');
            $table->string('empty_sale');
            $table->string('current_level')->default(0);
            $table->string('prev_level')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispensers');
    }
};
