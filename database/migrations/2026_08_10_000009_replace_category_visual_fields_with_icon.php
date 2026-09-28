<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->string('icono', 80)->default('category')->after('descripcion');
            $table->dropColumn(['imagen', 'color']);
        });
    }

    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->string('imagen')->nullable()->after('descripcion');
            $table->string('color', 50)->default('Celeste')->after('imagen');
            $table->dropColumn('icono');
        });
    }
};
