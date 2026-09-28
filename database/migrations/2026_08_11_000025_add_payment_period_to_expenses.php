<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->string('periodicidad', 20)->default('unico')->after('concepto');
            $table->date('periodo_desde')->nullable()->after('periodicidad');
            $table->date('periodo_hasta')->nullable()->after('periodo_desde');
        });
    }

    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropColumn(['periodicidad', 'periodo_desde', 'periodo_hasta']);
        });
    }
};
