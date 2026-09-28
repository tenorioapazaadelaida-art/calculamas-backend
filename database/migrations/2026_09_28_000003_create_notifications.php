<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('usuario_actor_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('titulo', 160);
            $table->string('mensaje', 500);
            $table->string('modulo', 60);
            $table->string('accion', 60);
            $table->string('entidad', 80)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->string('ruta', 160)->nullable();
            $table->timestamps();
            $table->index(['negocio_id', 'created_at']);
        });

        Schema::create('notificacion_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notificacion_id')->constrained('notificaciones')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->timestamp('leida_en')->nullable();
            $table->timestamps();
            $table->unique(['notificacion_id', 'usuario_id']);
            $table->index(['usuario_id', 'leida_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacion_usuario');
        Schema::dropIfExists('notificaciones');
    }
};
