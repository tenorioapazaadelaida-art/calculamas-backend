<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->renameColumn('name', 'nombre');
            $table->renameColumn('legal_name', 'razon_social');
            $table->renameColumn('business_type', 'tipo_negocio');
            $table->renameColumn('phone', 'telefono');
            $table->renameColumn('address', 'direccion');
            $table->renameColumn('currency', 'moneda');
            $table->renameColumn('timezone', 'zona_horaria');
            $table->renameColumn('taxes_enabled', 'impuestos_habilitados');
            $table->renameColumn('tax_regime', 'regimen_tributario');
            $table->renameColumn('active', 'activo');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->renameColumn('name', 'nombre');
            $table->renameColumn('email', 'correo');
            $table->renameColumn('active', 'activo');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->renameColumn('name', 'nombre');
            $table->renameColumn('slug', 'identificador');
            $table->renameColumn('system', 'sistema');
            $table->renameColumn('active', 'activo');
        });

        Schema::table('permisos', function (Blueprint $table) {
            $table->renameColumn('name', 'nombre');
            $table->renameColumn('slug', 'identificador');
            $table->renameColumn('module', 'modulo');
            $table->renameColumn('action', 'accion');
        });

        Schema::table('permiso_rol', fn (Blueprint $table) => $table->renameColumn('role_id', 'rol_id'));
        Schema::table('rol_usuario', fn (Blueprint $table) => $table->renameColumn('role_id', 'rol_id'));
        Schema::table('permiso_usuario', fn (Blueprint $table) => $table->renameColumn('allowed', 'permitido'));
    }

    public function down(): void
    {
        Schema::table('permiso_usuario', fn (Blueprint $table) => $table->renameColumn('permitido', 'allowed'));
        Schema::table('rol_usuario', fn (Blueprint $table) => $table->renameColumn('rol_id', 'role_id'));
        Schema::table('permiso_rol', fn (Blueprint $table) => $table->renameColumn('rol_id', 'role_id'));

        Schema::table('permisos', function (Blueprint $table) {
            $table->renameColumn('nombre', 'name');
            $table->renameColumn('identificador', 'slug');
            $table->renameColumn('modulo', 'module');
            $table->renameColumn('accion', 'action');
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->renameColumn('nombre', 'name');
            $table->renameColumn('identificador', 'slug');
            $table->renameColumn('sistema', 'system');
            $table->renameColumn('activo', 'active');
        });
        Schema::table('usuarios', function (Blueprint $table) {
            $table->renameColumn('nombre', 'name');
            $table->renameColumn('correo', 'email');
            $table->renameColumn('activo', 'active');
        });
        Schema::table('negocios', function (Blueprint $table) {
            $table->renameColumn('nombre', 'name');
            $table->renameColumn('razon_social', 'legal_name');
            $table->renameColumn('tipo_negocio', 'business_type');
            $table->renameColumn('telefono', 'phone');
            $table->renameColumn('direccion', 'address');
            $table->renameColumn('moneda', 'currency');
            $table->renameColumn('zona_horaria', 'timezone');
            $table->renameColumn('impuestos_habilitados', 'taxes_enabled');
            $table->renameColumn('regimen_tributario', 'tax_regime');
            $table->renameColumn('activo', 'active');
        });
    }
};
