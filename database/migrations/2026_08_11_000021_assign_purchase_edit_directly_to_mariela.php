<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permisoId = DB::table('permisos')->where('identificador', 'compras.editar')->value('id');
        $rolesCajero = DB::table('roles')->where('identificador', 'cajero')->pluck('id');
        DB::table('permiso_rol')->where('permiso_id', $permisoId)->whereIn('rol_id', $rolesCajero)->delete();

        $usuarios = DB::table('usuarios')->where('nombre', 'like', 'Mariela%')->pluck('id');
        foreach ($usuarios as $usuarioId) {
            DB::table('permiso_usuario')->updateOrInsert(
                ['usuario_id' => $usuarioId, 'permiso_id' => $permisoId],
                ['permitido' => true],
            );
        }
    }

    public function down(): void
    {
        $permisoId = DB::table('permisos')->where('identificador', 'compras.editar')->value('id');
        $usuarios = DB::table('usuarios')->where('nombre', 'like', 'Mariela%')->pluck('id');
        DB::table('permiso_usuario')->where('permiso_id', $permisoId)->whereIn('usuario_id', $usuarios)->delete();
    }
};
