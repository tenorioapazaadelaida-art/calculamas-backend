<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $t->string('nombre', 180);
            $t->string('nit', 30)->nullable();
            $t->string('telefono', 30)->nullable();
            $t->string('correo')->nullable();
            $t->string('direccion')->nullable();
            $t->boolean('activo')->default(true);
            $t->timestamps();
            $t->index(['negocio_id', 'nombre']);
        });
        Schema::create('compras', function (Blueprint $t) {
            $t->id();
            $t->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $t->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $t->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $t->string('numero', 40);
            $t->string('numero_factura', 80)->nullable();
            $t->date('fecha');
            $t->decimal('subtotal', 18, 2);
            $t->decimal('descuento', 18, 2)->default(0);
            $t->decimal('total', 18, 2);
            $t->decimal('credito_fiscal_iva', 18, 2)->default(0);
            $t->boolean('con_factura')->default(false);
            $t->string('estado', 20)->default('confirmada');
            $t->text('observacion')->nullable();
            $t->timestamps();
            $t->unique(['negocio_id', 'numero']);
            $t->index(['negocio_id', 'fecha']);
        });
        Schema::create('detalles_compra', function (Blueprint $t) {
            $t->id();
            $t->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $t->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $t->decimal('cantidad', 18, 4);
            $t->decimal('costo_unitario', 18, 4);
            $t->decimal('subtotal', 18, 2);
            $t->timestamps();
        });
        Schema::create('ventas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $t->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $t->string('numero', 40);
            $t->dateTime('fecha');
            $t->string('cliente_nombre', 180)->nullable();
            $t->string('cliente_nit', 30)->nullable();
            $t->decimal('subtotal', 18, 2);
            $t->decimal('descuento', 18, 2)->default(0);
            $t->decimal('total', 18, 2);
            $t->decimal('debito_fiscal_iva', 18, 2)->default(0);
            $t->decimal('impuesto_transacciones', 18, 2)->default(0);
            $t->boolean('con_factura')->default(false);
            $t->string('estado', 20)->default('confirmada');
            $t->string('metodo_pago', 30)->default('efectivo');
            $t->text('observacion')->nullable();
            $t->timestamps();
            $t->unique(['negocio_id', 'numero']);
            $t->index(['negocio_id', 'fecha']);
        });
        Schema::create('detalles_venta', function (Blueprint $t) {
            $t->id();
            $t->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $t->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $t->decimal('cantidad', 18, 4);
            $t->decimal('precio_unitario', 18, 2);
            $t->decimal('costo_unitario', 18, 4);
            $t->decimal('subtotal', 18, 2);
            $t->decimal('costo_total', 18, 2);
            $t->timestamps();
        });
        Schema::create('movimientos_inventario', function (Blueprint $t) {
            $t->id();
            $t->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $t->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $t->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $t->dateTime('fecha');
            $t->string('tipo', 30);
            $t->string('referencia_tipo', 40)->nullable();
            $t->unsignedBigInteger('referencia_id')->nullable();
            $t->decimal('entrada_cantidad', 18, 4)->default(0);
            $t->decimal('entrada_costo_unitario', 18, 4)->default(0);
            $t->decimal('entrada_total', 18, 2)->default(0);
            $t->decimal('salida_cantidad', 18, 4)->default(0);
            $t->decimal('salida_costo_unitario', 18, 4)->default(0);
            $t->decimal('salida_total', 18, 2)->default(0);
            $t->decimal('saldo_cantidad', 18, 4);
            $t->decimal('saldo_costo_promedio', 18, 4);
            $t->decimal('saldo_total', 18, 2);
            $t->text('observacion')->nullable();
            $t->timestamps();
            $t->index(['negocio_id', 'producto_id', 'fecha'], 'kardex_consulta');
        });
        Schema::create('categorias_gastos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $t->string('nombre', 120);
            $t->text('descripcion')->nullable();
            $t->boolean('deducible_iue')->default(true);
            $t->boolean('activo')->default(true);
            $t->timestamps();
            $t->unique(['negocio_id', 'nombre']);
        });
        Schema::create('gastos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $t->foreignId('categoria_gasto_id')->constrained('categorias_gastos')->restrictOnDelete();
            $t->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $t->date('fecha');
            $t->string('concepto', 200);
            $t->string('proveedor', 180)->nullable();
            $t->string('numero_documento', 80)->nullable();
            $t->decimal('monto', 18, 2);
            $t->decimal('credito_fiscal_iva', 18, 2)->default(0);
            $t->boolean('con_factura')->default(false);
            $t->boolean('deducible_iue')->default(true);
            $t->string('estado', 20)->default('registrado');
            $t->text('observacion')->nullable();
            $t->timestamps();
            $t->index(['negocio_id', 'fecha']);
        });
        Schema::create('configuraciones_impuestos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $t->string('codigo', 20);
            $t->string('nombre', 100);
            $t->decimal('alicuota', 8, 4);
            $t->string('periodicidad', 20);
            $t->boolean('incluido_en_precio')->default(false);
            $t->boolean('activo')->default(true);
            $t->date('vigente_desde');
            $t->date('vigente_hasta')->nullable();
            $t->timestamps();
            $t->unique(['negocio_id', 'codigo', 'vigente_desde'], 'impuesto_vigencia');
        });
        Schema::create('impuestos_generados', function (Blueprint $t) {
            $t->id();
            $t->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $t->string('codigo', 20);
            $t->unsignedSmallInteger('gestion');
            $t->unsignedTinyInteger('periodo')->nullable();
            $t->date('desde');
            $t->date('hasta');
            $t->decimal('base_imponible', 18, 2);
            $t->decimal('debito_fiscal', 18, 2)->default(0);
            $t->decimal('credito_fiscal', 18, 2)->default(0);
            $t->decimal('saldo_anterior', 18, 2)->default(0);
            $t->decimal('importe_determinado', 18, 2);
            $t->string('estado', 20)->default('calculado');
            $t->timestamps();
            $t->unique(['negocio_id', 'codigo', 'gestion', 'periodo']);
        });
    }

    public function down(): void
    {
        foreach (['impuestos_generados', 'configuraciones_impuestos', 'gastos', 'categorias_gastos', 'movimientos_inventario', 'detalles_venta', 'ventas', 'detalles_compra', 'compras', 'proveedores'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
