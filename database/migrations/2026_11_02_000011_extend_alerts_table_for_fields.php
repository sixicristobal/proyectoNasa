<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La tabla `alerts` ya existía con el esquema del flujo n8n/Ollama
     * (risk_type, message, data_date, source, read_at). Se agregan las
     * columnas del desafío sin renombrar las existentes para no romper
     * CheckAgroAlerts ni el dashboard actual.
     */
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->unsignedBigInteger('field_id')->nullable()->after('id')->index();
            $table->string('severity', 10)->default('media')->after('risk_type');
            $table->unsignedBigInteger('triggered_by_observation_id')->nullable()->after('source')->index();
            $table->date('valid_from')->nullable()->after('data_date');
            $table->date('valid_until')->nullable()->after('valid_from');
            $table->timestamp('acknowledged_at')->nullable()->after('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn([
                'field_id',
                'severity',
                'triggered_by_observation_id',
                'valid_from',
                'valid_until',
                'acknowledged_at',
            ]);
        });
    }
};
