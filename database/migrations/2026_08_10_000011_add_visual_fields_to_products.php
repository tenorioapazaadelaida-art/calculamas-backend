<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('subcategoria', 120)->nullable()->after('categoria_id');
            $table->string('imagen')->nullable()->after('descripcion');
            $table->string('color', 40)->nullable()->after('imagen');
        });
    }

    public function down(): void
    {
        Schema::table('productos', fn (Blueprint $table) => $table->dropColumn(['subcategoria', 'imagen', 'color']));
    }
};
