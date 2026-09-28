<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();
        DB::table('permisos')->updateOrInsert(
            ['identificador' => 'compras.editar'],
            ['nombre' => 'Editar Compras', 'modulo' => 'compras', 'accion' => 'editar', 'created_at' => $ahora, 'updated_at' => $ahora],
        );
        $permisoId = DB::table('permisos')->where('identificador', 'compras.editar')->value('id');
        $roles = DB::table('roles')->where('identificador', 'administrador')->pluck('id');
        foreach ($roles as $rolId) {
            DB::table('permiso_rol')->insertOrIgnore(['rol_id' => $rolId, 'permiso_id' => $permisoId]);
        }
    }

    public function down(): void
    {
        $id = DB::table('permisos')->where('identificador', 'compras.editar')->value('id');
        if ($id) {
            DB::table('permiso_rol')->where('permiso_id', $id)->delete();
        }
        DB::table('permisos')->where('identificador', 'compras.editar')->delete();
    }
};
