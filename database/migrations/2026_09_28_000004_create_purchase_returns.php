<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devoluciones_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('compra_id')->constrained('compras')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('numero', 50);
            $table->dateTime('fecha');
            $table->string('motivo', 500);
            $table->string('solucion', 30);
            $table->string('medio_reembolso', 30)->nullable();
            $table->boolean('con_nota_credito_debito')->default(false);
            $table->string('numero_nota_credito_debito', 80)->nullable();
            $table->decimal('total', 18, 2);
            $table->decimal('ajuste_credito_fiscal_iva', 18, 2)->default(0);
            $table->string('estado', 20)->default('confirmada');
            $table->timestamps();
            $table->unique(['negocio_id', 'numero']);
            $table->index(['negocio_id', 'fecha']);
        });

        Schema::create('detalles_devolucion_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_compra_id')->constrained('devoluciones_compra')->cascadeOnDelete();
            $table->foreignId('detalle_compra_id')->constrained('detalles_compra')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->decimal('cantidad', 18, 4);
            $table->decimal('costo_compra_unitario', 18, 4);
            $table->decimal('costo_inventario_unitario', 18, 4);
            $table->decimal('subtotal', 18, 2);
            $table->decimal('costo_inventario_total', 18, 2);
            $table->timestamps();
        });

        DB::table('permisos')->updateOrInsert(
            ['identificador' => 'compras.devolver'],
            ['nombre' => 'Registrar devoluciones de compras', 'modulo' => 'compras', 'accion' => 'devolver', 'created_at' => now(), 'updated_at' => now()],
        );
        $permisoId = DB::table('permisos')->where('identificador', 'compras.devolver')->value('id');
        $administradores = DB::table('roles')->where('identificador', 'administrador')->pluck('id');
        foreach ($administradores as $rolId) {
            DB::table('permiso_rol')->insertOrIgnore(['rol_id' => $rolId, 'permiso_id' => $permisoId]);
        }
    }

    public function down(): void
    {
        $permisoId = DB::table('permisos')->where('identificador', 'compras.devolver')->value('id');
        if ($permisoId) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso_usuario')->where('permiso_id', $permisoId)->delete();
            DB::table('permisos')->where('id', $permisoId)->delete();
        }
        Schema::dropIfExists('detalles_devolucion_compra');
        Schema::dropIfExists('devoluciones_compra');
    }
};
