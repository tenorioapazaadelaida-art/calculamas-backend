<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalles_compra', function (Blueprint $table) {
            $table->string('presentacion', 12)->default('unidad');
            $table->decimal('cantidad_presentacion', 18, 4)->nullable();
            $table->unsignedInteger('unidades_por_paquete')->default(1);
            $table->decimal('costo_presentacion', 18, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('detalles_compra', fn (Blueprint $table) => $table->dropColumn([
            'presentacion', 'cantidad_presentacion', 'unidades_por_paquete', 'costo_presentacion',
        ]));
    }
};
