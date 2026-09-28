<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devoluciones_venta', function (Blueprint $table) {
            $table->boolean('con_nota_credito_debito')->default(false)->after('metodo_reembolso');
            $table->string('numero_nota_credito_debito', 80)->nullable()->after('con_nota_credito_debito');
            $table->decimal('ajuste_debito_fiscal_iva', 18, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('devoluciones_venta', function (Blueprint $table) {
            $table->dropColumn(['con_nota_credito_debito', 'numero_nota_credito_debito', 'ajuste_debito_fiscal_iva']);
        });
    }
};
