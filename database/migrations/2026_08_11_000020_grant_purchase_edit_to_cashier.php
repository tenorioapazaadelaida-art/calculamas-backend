<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permisoId = DB::table('permisos')->where('identificador', 'compras.editar')->value('id');
        $roles = DB::table('roles')->where('identificador', 'cajero')->pluck('id');
        foreach ($roles as $rolId) {
            DB::table('permiso_rol')->insertOrIgnore(['rol_id' => $rolId, 'permiso_id' => $permisoId]);
        }
    }

    public function down(): void
    {
        $permisoId = DB::table('permisos')->where('identificador', 'compras.editar')->value('id');
        $roles = DB::table('roles')->where('identificador', 'cajero')->pluck('id');
        DB::table('permiso_rol')->where('permiso_id', $permisoId)->whereIn('rol_id', $roles)->delete();
    }
};
