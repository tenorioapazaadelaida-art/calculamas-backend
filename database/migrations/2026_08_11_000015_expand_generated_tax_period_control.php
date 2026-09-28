<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impuestos_generados', function (Blueprint $table) {
            $table->decimal('saldo_favor', 18, 2)->default(0)->after('importe_determinado');
            $table->timestamp('cerrado_en')->nullable()->after('estado');
            $table->string('numero_declaracion', 80)->nullable()->after('cerrado_en');
            $table->date('fecha_pago')->nullable()->after('numero_declaracion');
        });
    }

    public function down(): void
    {
        Schema::table('impuestos_generados', fn (Blueprint $table) => $table->dropColumn(['saldo_favor', 'cerrado_en', 'numero_declaracion', 'fecha_pago']));
    }
};
