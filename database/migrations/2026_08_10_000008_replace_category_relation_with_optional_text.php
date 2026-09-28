<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->string('subcategoria', 120)->nullable()->after('nombre');
            $table->dropConstrainedForeignId('subcategoria_id');
        });
    }

    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->foreignId('subcategoria_id')->nullable()->after('nombre')->constrained('categorias')->nullOnDelete();
            $table->dropColumn('subcategoria');
        });
    }
};
