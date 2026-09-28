<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->boolean('iva_habilitado')->default(false)->after('regimen_tributario');
            $table->boolean('it_habilitado')->default(false)->after('iva_habilitado');
            $table->boolean('iue_habilitado')->default(false)->after('it_habilitado');
        });
        DB::table('negocios')->where('impuestos_habilitados', true)->update(['iva_habilitado' => true]);
    }

    public function down(): void
    {
        Schema::table('negocios', fn (Blueprint $table) => $table->dropColumn(['iva_habilitado', 'it_habilitado', 'iue_habilitado']));
    }
};
