<?php

use App\Models\Usuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\\Models\\User')
            ->update(['tokenable_type' => Usuario::class]);
    }

    public function down(): void
    {
        DB::table('personal_access_tokens')
            ->where('tokenable_type', Usuario::class)
            ->update(['tokenable_type' => 'App\\Models\\User']);
    }
};
