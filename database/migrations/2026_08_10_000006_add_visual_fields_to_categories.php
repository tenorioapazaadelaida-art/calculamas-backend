<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->string('codigo_corto', 20)->nullable()->after('negocio_id');
            $table->foreignId('subcategoria_id')->nullable()->after('nombre')->constrained('categorias')->nullOnDelete();
            $table->string('imagen')->nullable()->after('descripcion');
            $table->string('color', 7)->default('#5F8F94')->after('imagen');
            $table->unique(['negocio_id', 'codigo_corto']);
        });
    }

    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropUnique(['negocio_id', 'codigo_corto']);
            $table->dropConstrainedForeignId('subcategoria_id');
            $table->dropColumn(['codigo_corto', 'imagen', 'color']);
        });
    }
};
