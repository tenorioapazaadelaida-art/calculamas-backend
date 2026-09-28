<?php

use App\Models\Permiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_utilidad', function (Blueprint $t) {
            $t->id();
            $t->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $t->string('nombre', 120);
            $t->decimal('porcentaje_general', 8, 2);
            $t->boolean('predeterminada')->default(false);
            $t->boolean('activo')->default(true);
            $t->foreignId('usuario_registro_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $t->timestamps();
            $t->unique(['negocio_id', 'nombre']);
        });
        Schema::create('producto_tipo_utilidad', function (Blueprint $t) {
            $t->id();
            $t->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $t->foreignId('tipo_utilidad_id')->constrained('tipos_utilidad')->cascadeOnDelete();
            $t->decimal('porcentaje', 8, 2);
            $t->boolean('activo')->default(true);
            $t->timestamps();
            $t->unique(['producto_id', 'tipo_utilidad_id']);
        });
        Schema::table('detalles_venta', function (Blueprint $t) {
            $t->foreignId('tipo_utilidad_id')->nullable()->after('producto_id')->constrained('tipos_utilidad')->nullOnDelete();
            $t->string('tipo_utilidad_nombre', 120)->nullable()->after('tipo_utilidad_id');
            $t->decimal('porcentaje_utilidad', 8, 2)->nullable()->after('tipo_utilidad_nombre');
        });
        foreach (['ver', 'crear', 'editar', 'desactivar'] as $a) {
            $p = Permiso::firstOrCreate(['identificador' => "utilidades.$a"], ['nombre' => ucfirst($a).' Tipos de utilidad', 'modulo' => 'utilidades', 'accion' => $a]);
            $roles = DB::table('roles')->where('identificador', 'administrador')->pluck('id');
            foreach ($roles as $r) {
                DB::table('permiso_rol')->updateOrInsert(['rol_id' => $r, 'permiso_id' => $p->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('detalles_venta', function (Blueprint $t) {
            $t->dropConstrainedForeignId('tipo_utilidad_id');
            $t->dropColumn(['tipo_utilidad_nombre', 'porcentaje_utilidad']);
        });
        Schema::dropIfExists('producto_tipo_utilidad');
        Schema::dropIfExists('tipos_utilidad');
    }
};
