<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['calculo_final', 'comercial', 'costo_mano_obra', 'costo_materia_prima', 'gastos_indirectos', 'imagenes', 'impuesto', 'inventarios', 'produccion', 'producto_emprendimientos', 'productos', 'usuarios', 'emprendimientos'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::rename('businesses', 'negocios');
        Schema::rename('users', 'usuarios');
        Schema::rename('permissions', 'permisos');
        Schema::rename('permission_role', 'permiso_rol');
        Schema::rename('permission_user', 'permiso_usuario');
        Schema::rename('role_user', 'rol_usuario');
        Schema::table('usuarios', fn (Blueprint $table) => $table->renameColumn('business_id', 'negocio_id'));
        Schema::table('roles', fn (Blueprint $table) => $table->renameColumn('business_id', 'negocio_id'));
        Schema::table('permiso_rol', fn (Blueprint $table) => $table->renameColumn('permission_id', 'permiso_id'));
        Schema::table('permiso_usuario', function (Blueprint $table) {
            $table->renameColumn('permission_id', 'permiso_id');
            $table->renameColumn('user_id', 'usuario_id');
        });
        Schema::table('rol_usuario', fn (Blueprint $table) => $table->renameColumn('user_id', 'usuario_id'));
        Schema::enableForeignKeyConstraints();

        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['negocio_id', 'nombre']);
        });

        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->string('codigo', 60);
            $table->string('codigo_barras', 80)->nullable();
            $table->string('nombre', 180);
            $table->text('descripcion')->nullable();
            $table->decimal('precio_venta', 18, 2)->default(0);
            $table->decimal('stock_actual', 18, 4)->default(0);
            $table->decimal('stock_minimo', 18, 4)->default(0);
            $table->decimal('costo_promedio', 18, 4)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['negocio_id', 'codigo']);
            $table->unique(['negocio_id', 'codigo_barras']);
            $table->index(['negocio_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
        Schema::dropIfExists('categorias');
    }
};
