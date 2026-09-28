<?php

namespace Tests\Feature;

use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TiposUtilidadTest extends TestCase
{
    use RefreshDatabase;

    public function test_tipo_activo_se_aplica_y_conserva_su_porcentaje_en_la_venta(): void
    {
        $this->seed(PermisoSeeder::class);
        $sesion = $this->postJson('/api/auth/register', [
            'nombre_negocio' => 'Negocio utilidad', 'tipo_negocio' => 'Comercio', 'nombre' => 'Admin',
            'correo' => 'utilidad@prueba.test', 'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated()->json();
        $token = $sesion['token'];

        $producto = $this->withToken($token)->postJson('/api/productos', [
            'codigo' => 'FUN-1', 'nombre' => 'Funda', 'precio_venta' => 0,
        ])->assertCreated()->json();
        $this->withToken($token)->postJson('/api/compras', [
            'fecha' => '2026-08-12',
            'detalles' => [['producto_id' => $producto['id'], 'cantidad' => 5, 'costo_unitario' => 100]],
        ])->assertCreated();

        $tipo = $this->withToken($token)->postJson('/api/tipos-utilidad', [
            'nombre' => 'Promoción', 'porcentaje_general' => 10,
            'predeterminada' => true, 'activo' => true,
            'productos' => [['producto_id' => $producto['id'], 'porcentaje' => 8, 'activo' => true]],
        ])->assertCreated()->assertJsonPath('activo', true)->json();

        $this->withToken($token)->postJson('/api/ventas', [
            'fecha' => '2026-08-12',
            'detalles' => [[
                'producto_id' => $producto['id'], 'tipo_utilidad_id' => $tipo['id'],
                'porcentaje_utilidad' => 999, 'cantidad' => 1, 'precio_unitario' => 108,
            ]],
        ])->assertCreated()->assertJsonPath('detalles.0.tipo_utilidad_nombre', 'Promoción')
            ->assertJsonPath('detalles.0.porcentaje_utilidad', 8);

        $this->assertDatabaseHas('detalles_venta', [
            'tipo_utilidad_id' => $tipo['id'], 'tipo_utilidad_nombre' => 'Promoción',
            'porcentaje_utilidad' => 8,
        ]);

        $this->withToken($token)->putJson("/api/tipos-utilidad/{$tipo['id']}", [
            'nombre' => 'Promoción', 'porcentaje_general' => 10,
            'predeterminada' => true, 'activo' => true,
            'productos' => [['producto_id' => $producto['id'], 'porcentaje' => 8, 'activo' => false]],
        ])->assertOk()->assertJsonPath('productos.0.pivot.activo', 0);

        $this->withToken($token)->postJson('/api/ventas', [
            'fecha' => '2026-08-12',
            'detalles' => [[
                'producto_id' => $producto['id'], 'tipo_utilidad_id' => $tipo['id'],
                'cantidad' => 1, 'precio_unitario' => 110,
            ]],
        ])->assertCreated()->assertJsonPath('detalles.0.porcentaje_utilidad', 10)
            ->assertJsonPath('detalles.0.precio_unitario', 110);

        $this->assertDatabaseHas('auditorias', [
            'negocio_id' => $sesion['usuario']['negocio_id'],
            'entidad' => 'tipos_utilidad',
            'entidad_id' => $tipo['id'],
            'accion' => 'editar',
        ]);

        $this->withToken($token)->patchJson("/api/tipos-utilidad/{$tipo['id']}/estado", ['activo' => false])
            ->assertOk()->assertJsonPath('activo', false);
        $this->withToken($token)->postJson('/api/ventas', [
            'detalles' => [[
                'producto_id' => $producto['id'], 'tipo_utilidad_id' => $tipo['id'],
                'cantidad' => 1, 'precio_unitario' => 108,
            ]],
        ])->assertUnprocessable();
    }

    public function test_personal_de_ventas_ve_opciones_activas_pero_no_las_administra(): void
    {
        $this->seed(PermisoSeeder::class);
        $sesion = $this->postJson('/api/auth/register', [
            'nombre_negocio' => 'Negocio permisos', 'tipo_negocio' => 'Comercio', 'nombre' => 'Admin',
            'correo' => 'admin-permisos@prueba.test', 'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated()->json();
        $token = $sesion['token'];

        $this->withToken($token)->postJson('/api/tipos-utilidad', [
            'nombre' => 'Normal', 'porcentaje_general' => 30, 'activo' => true,
        ])->assertCreated();
        $producto = $this->withToken($token)->postJson('/api/productos', [
            'codigo' => 'SEG-1', 'nombre' => 'Producto seguro', 'precio_venta' => 0,
        ])->assertCreated()->json();
        $this->withToken($token)->postJson('/api/compras', [
            'fecha' => '2026-08-12',
            'detalles' => [['producto_id' => $producto['id'], 'cantidad' => 2, 'costo_unitario' => 100]],
        ])->assertCreated();
        $inactiva = $this->withToken($token)->postJson('/api/tipos-utilidad', [
            'nombre' => 'Promoción terminada', 'porcentaje_general' => 5, 'activo' => false,
        ])->assertCreated()->json();

        $cajero = collect($this->withToken($token)->getJson('/api/roles')->json())
            ->firstWhere('identificador', 'cajero');
        $this->withToken($token)->putJson("/api/roles/{$cajero['id']}/permisos", [
            'permisos' => ['ventas.ver', 'ventas.crear', 'productos.ver', 'utilidades.ver', 'utilidades.crear', 'utilidades.editar', 'utilidades.desactivar'],
        ])->assertOk();
        $this->withToken($token)->postJson('/api/usuarios', [
            'nombre' => 'Personal de ventas', 'correo' => 'ventas@prueba.test',
            'password' => 'Password123!', 'roles' => [$cajero['id']],
        ])->assertCreated();
        $login = $this->flushHeaders()->postJson('/api/auth/login', [
            'nombre_negocio' => 'Negocio permisos', 'correo' => 'ventas@prueba.test',
            'password' => 'Password123!',
        ])->assertOk()->json();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->withToken($login['token'])->getJson('/api/tipos-utilidad-opciones')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.nombre', 'Normal');
        $this->app['auth']->forgetGuards();
        $productoCajero = $this->flushHeaders()->withToken($login['token'])->getJson('/api/productos')
            ->assertOk()->assertJsonMissingPath('0.costo_promedio')
            ->assertJsonPath('0.precio_venta_calculado', 130)->json('0');
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->withToken($login['token'])->postJson('/api/ventas', [
            'detalles' => [['producto_id' => $productoCajero['id'], 'tipo_utilidad_id' => $productoCajero['tipo_utilidad_id'], 'cantidad' => 1, 'precio_unitario' => 1]],
        ])->assertCreated()->assertJsonPath('detalles.0.precio_unitario', 130);
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->withToken($login['token'])->getJson('/api/tipos-utilidad')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->withToken($login['token'])
            ->patchJson("/api/tipos-utilidad/{$inactiva['id']}/estado", ['activo' => true])
            ->assertForbidden();
    }
}
