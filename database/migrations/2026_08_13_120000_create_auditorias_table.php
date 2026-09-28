<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->nullable()->constrained('negocios')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('accion', 30);
            $table->string('entidad', 120);
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->string('direccion_ip', 45)->nullable();
            $table->text('agente_usuario')->nullable();
            $table->timestamps();
            $table->index(['negocio_id', 'entidad', 'entidad_id']);
            $table->index(['negocio_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
