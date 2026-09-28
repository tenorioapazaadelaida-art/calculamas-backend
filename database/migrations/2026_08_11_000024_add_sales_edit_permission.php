<?php

use App\Models\Permiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permiso = Permiso::firstOrCreate(['identificador' => 'ventas.editar'], ['nombre' => 'Editar Ventas', 'modulo' => 'ventas', 'accion' => 'editar']);
        $roles = DB::table('permiso_rol')->join('permisos', 'permisos.id', '=', 'permiso_rol.permiso_id')->where('permisos.identificador', 'ventas.crear')->pluck('permiso_rol.rol_id');
        foreach ($roles as $rolId) {
            DB::table('permiso_rol')->updateOrInsert(['rol_id' => $rolId, 'permiso_id' => $permiso->id]);
        }
    }

    public function down(): void
    {
        if ($id = Permiso::where('identificador', 'ventas.editar')->value('id')) {
            DB::table('permiso_rol')->where('permiso_id', $id)->delete();
            DB::table('permiso_usuario')->where('permiso_id', $id)->delete();
            Permiso::whereKey($id)->delete();
        }
    }
};
