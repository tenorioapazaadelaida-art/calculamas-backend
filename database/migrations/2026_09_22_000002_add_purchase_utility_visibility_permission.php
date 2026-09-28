<?php

use App\Models\Permiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Permiso::firstOrCreate(
            ['identificador' => 'utilidades.ver_en_compras'],
            [
                'nombre' => 'Ver utilidad asignada en compras',
                'modulo' => 'utilidades',
                'accion' => 'ver_en_compras',
            ],
        );
    }

    public function down(): void
    {
        $id = Permiso::where('identificador', 'utilidades.ver_en_compras')->value('id');
        if ($id) {
            DB::table('permiso_rol')->where('permiso_id', $id)->delete();
            DB::table('permiso_usuario')->where('permiso_id', $id)->delete();
            Permiso::whereKey($id)->delete();
        }
    }
};
