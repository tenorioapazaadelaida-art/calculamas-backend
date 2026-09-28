<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->string('color', 50)->default('Celeste')->change();
        });
        DB::table('categorias')->where('color', '#5F8F94')->update(['color' => 'Celeste']);
    }

    public function down(): void
    {
        DB::table('categorias')->where('color', 'Celeste')->update(['color' => '#5F8F94']);
        Schema::table('categorias', function (Blueprint $table) {
            $table->string('color', 7)->default('#5F8F94')->change();
        });
    }
};
