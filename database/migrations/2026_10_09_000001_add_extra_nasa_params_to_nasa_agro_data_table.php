<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega parámetros extendidos de NASA POWER para detección de
     * lluvias/tormentas/heladas: T2M_MAX, T2M_MIN, WS2M, ALLSKY_SFC_SW_DWN.
     * Todas nullable para no romper filas existentes.
     */
    public function up(): void
    {
        Schema::table('nasa_agro_data', function (Blueprint $table) {
            $table->float('temp_max')->nullable()->after('temp');
            $table->float('temp_min')->nullable()->after('temp_max');
            $table->float('wind_speed')->nullable()->after('humidity')->comment('WS2M en m/s');
            $table->float('solar_radiation')->nullable()->after('wind_speed')->comment('ALLSKY_SFC_SW_DWN');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nasa_agro_data', function (Blueprint $table) {
            $table->dropColumn(['temp_max', 'temp_min', 'wind_speed', 'solar_radiation']);
        });
    }
};
