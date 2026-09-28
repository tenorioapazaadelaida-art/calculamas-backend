<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', fn (Blueprint $table) => $table->unsignedInteger('numero_registro')->nullable()->after('numero'));
        $negocios = DB::table('compras')->distinct()->pluck('negocio_id');
        foreach ($negocios as $negocioId) {
            $numero = 1;
            foreach (DB::table('compras')->where('negocio_id', $negocioId)->orderBy('id')->pluck('id') as $id) {
                DB::table('compras')->where('id', $id)->update(['numero_registro' => $numero++]);
            }
        }
        Schema::table('compras', fn (Blueprint $table) => $table->unique(['negocio_id', 'numero_registro']));
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropUnique(['negocio_id', 'numero_registro']);
            $table->dropColumn('numero_registro');
        });
    }
};
