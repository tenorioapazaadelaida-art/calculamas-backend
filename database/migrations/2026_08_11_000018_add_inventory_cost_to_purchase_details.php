<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalles_compra', function (Blueprint $table) {
            $table->decimal('costo_inventario_unitario', 18, 4)->nullable()->after('costo_unitario');
            $table->decimal('subtotal_inventario', 18, 2)->nullable()->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('detalles_compra', fn (Blueprint $table) => $table->dropColumn(['costo_inventario_unitario', 'subtotal_inventario']));
    }
};
