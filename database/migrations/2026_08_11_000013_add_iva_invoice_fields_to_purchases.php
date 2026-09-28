<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->string('proveedor_nit', 30)->nullable()->after('proveedor_nombre');
            $table->string('cuf_autorizacion', 120)->nullable()->after('numero_factura');
            $table->decimal('importe_no_sujeto_iva', 18, 2)->default(0)->after('subtotal');
            $table->decimal('base_credito_fiscal', 18, 2)->default(0)->after('descuento');
        });
    }

    public function down(): void
    {
        Schema::table('compras', fn (Blueprint $table) => $table->dropColumn(['proveedor_nit', 'cuf_autorizacion', 'importe_no_sujeto_iva', 'base_credito_fiscal']));
    }
};
