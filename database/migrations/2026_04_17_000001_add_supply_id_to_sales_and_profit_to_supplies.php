<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales', 'supply_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->unsignedBigInteger('supply_id')->nullable()->after('dispenser_id');
            });
        }

        if (! Schema::hasColumn('supplies', 'profit')) {
            Schema::table('supplies', function (Blueprint $table) {
                $table->decimal('profit', 15, 2)->default(0)->after('excess_kg');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales', 'supply_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('supply_id');
            });
        }

        if (Schema::hasColumn('supplies', 'profit')) {
            Schema::table('supplies', function (Blueprint $table) {
                $table->dropColumn('profit');
            });
        }
    }
};
