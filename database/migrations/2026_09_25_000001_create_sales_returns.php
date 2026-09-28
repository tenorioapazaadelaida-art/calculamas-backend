<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devoluciones_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('numero', 50);
            $table->dateTime('fecha');
            $table->string('motivo', 500);
            $table->string('metodo_reembolso', 30);
            $table->decimal('total', 18, 2);
            $table->string('estado', 20)->default('confirmada');
            $table->timestamps();
            $table->unique(['negocio_id', 'numero']);
            $table->index(['negocio_id', 'fecha']);
        });

        Schema::create('detalles_devolucion_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_venta_id')->constrained('devoluciones_venta')->cascadeOnDelete();
            $table->foreignId('detalle_venta_id')->constrained('detalles_venta')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->decimal('cantidad', 18, 4);
            $table->decimal('precio_unitario', 18, 2);
            $table->decimal('costo_unitario', 18, 4);
            $table->decimal('subtotal', 18, 2);
            $table->decimal('costo_total', 18, 2);
            $table->boolean('reintegrar_stock')->default(true);
            $table->timestamps();
        });

        DB::table('permisos')->updateOrInsert(
            ['identificador' => 'ventas.devolver'],
            ['nombre' => 'Registrar devoluciones de ventas', 'modulo' => 'ventas', 'accion' => 'devolver', 'updated_at' => now(), 'created_at' => now()],
        );

        $permisoId = DB::table('permisos')->where('identificador', 'ventas.devolver')->value('id');
        $administradores = DB::table('roles')->whereRaw('LOWER(nombre) = ?', ['administrador'])->pluck('id');
        foreach ($administradores as $rolId) {
            DB::table('permiso_rol')->insertOrIgnore(['rol_id' => $rolId, 'permiso_id' => $permisoId]);
        }
    }

    public function down(): void
    {
        $permisoId = DB::table('permisos')->where('identificador', 'ventas.devolver')->value('id');
        if ($permisoId) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso_usuario')->where('permiso_id', $permisoId)->delete();
            DB::table('permisos')->where('id', $permisoId)->delete();
        }
        Schema::dropIfExists('detalles_devolucion_venta');
        Schema::dropIfExists('devoluciones_venta');
    }
};
