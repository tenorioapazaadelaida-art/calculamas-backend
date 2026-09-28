<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categorias')->where('icono', 'cases')->update(['icono' => 'phone_case']);
    }

    public function down(): void
    {
        DB::table('categorias')->where('icono', 'phone_case')->update(['icono' => 'cases']);
    }
};
