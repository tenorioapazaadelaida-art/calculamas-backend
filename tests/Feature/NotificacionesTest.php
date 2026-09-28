<?php

namespace Tests\Feature;

use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_operaciones_generan_notificaciones_separadas_por_negocio(): void
    {
        $this->seed(PermisoSeeder::class);
        $primero = $this->postJson('/api/auth/register', [
            'nombre_negocio' => 'Negocio Uno', 'tipo_negocio' => 'Comercio', 'nombre' => 'Administradora Uno',
            'correo' => 'uno@notificaciones.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertCreated()->json();
        $segundo = $this->postJson('/api/auth/register', [
            'nombre_negocio' => 'Negocio Dos', 'tipo_negocio' => 'Comercio', 'nombre' => 'Administrador Dos',
            'correo' => 'dos@notificaciones.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertCreated()->json();

        $categoria = $this->withToken($primero['token'])->postJson('/api/categorias', [
            'nombre' => 'Accesorios', 'icono' => 'category',
        ])->assertCreated()->json();
        $this->withToken($primero['token'])->postJson('/api/productos', [
            'codigo' => 'NOT-1', 'nombre' => 'Producto notificado', 'categoria_id' => $categoria['id'], 'precio_venta' => 20,
        ])->assertCreated();

        $notificaciones = $this->withToken($primero['token'])->getJson('/api/notificaciones')
            ->assertOk()->assertJsonCount(2)->assertJsonPath('0.leida', false);
        $id = $notificaciones->json('0.id');
        $this->withToken($primero['token'])->patchJson("/api/notificaciones/{$id}/leer")->assertOk();
        $this->withToken($primero['token'])->getJson('/api/notificaciones')
            ->assertOk()->assertJsonPath('0.leida', true);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->withToken($segundo['token'])->getJson('/api/notificaciones')
            ->assertOk()
            ->assertJsonMissing(['entidad' => 'categorias', 'entidad_id' => $categoria['id']])
            ->assertJsonMissing(['mensaje' => 'Administradora Uno registró producto “Producto notificado”.']);
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->withToken($segundo['token'])->patchJson("/api/notificaciones/{$id}/leer")
            ->assertNotFound();
    }
}
