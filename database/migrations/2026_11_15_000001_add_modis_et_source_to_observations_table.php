<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El job de MODIS extendido persiste LST y ET; ET no estaba en el enum.
     */
    public function up(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            $table->enum('source', [
                'POWER',
                'MOD13Q1',
                'SMAP',
                'ECOSTRESS',
                'GPM',
                'SRTM',
                'MODIS_LST',
                'MODIS_ET',
                'LANDSAT',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            $table->enum('source', [
                'POWER',
                'MOD13Q1',
                'SMAP',
                'ECOSTRESS',
                'GPM',
                'SRTM',
                'MODIS_LST',
                'LANDSAT',
            ])->change();
        });
    }
};
