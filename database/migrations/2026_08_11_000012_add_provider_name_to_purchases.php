<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', fn (Blueprint $table) => $table->string('proveedor_nombre', 180)->nullable()->after('proveedor_id'));
    }

    public function down(): void
    {
        Schema::table('compras', fn (Blueprint $table) => $table->dropColumn('proveedor_nombre'));
    }
};
