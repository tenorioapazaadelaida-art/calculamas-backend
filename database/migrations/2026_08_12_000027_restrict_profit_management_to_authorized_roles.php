<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permisos = DB::table('permisos')->where('identificador', 'like', 'utilidades.%')->pluck('id');
        $rolesNoAdministradores = DB::table('roles')->where('identificador', '!=', 'administrador')->pluck('id');

        DB::table('permiso_rol')->whereIn('permiso_id', $permisos)->whereIn('rol_id', $rolesNoAdministradores)->delete();
    }

    public function down(): void
    {
        // No se reasignan permisos automáticamente: deben concederse de forma explícita.
    }
};
