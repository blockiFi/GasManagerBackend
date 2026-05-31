<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('dispensers', 'cash_meter_modulus')) {
            Schema::table('dispensers', function (Blueprint $table) {
                $table->dropColumn('cash_meter_modulus');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('dispensers', 'cash_meter_modulus')) {
            Schema::table('dispensers', function (Blueprint $table) {
                $table->unsignedBigInteger('cash_meter_modulus')->nullable()->after('prev_level');
            });
        }
    }
};
