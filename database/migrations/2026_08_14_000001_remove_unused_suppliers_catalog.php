<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proveedor_id');
        });

        Schema::dropIfExists('proveedores');
    }

    public function down(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->string('nombre', 180);
            $table->string('nit', 30)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo')->nullable();
            $table->string('direccion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['negocio_id', 'nombre']);
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->foreignId('proveedor_id')->nullable()->after('negocio_id')
                ->constrained('proveedores')->nullOnDelete();
        });
    }
};
