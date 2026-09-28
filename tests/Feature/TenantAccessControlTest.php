<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisoSeeder::class);
    }

    public function test_category_with_registered_products_cannot_be_deactivated(): void
    {
        $session = $this->register('Mega Fundas', 'mega@test.local');
        $negocioId = $session['usuario']['negocio_id'];
        $categoria = Categoria::create([
            'negocio_id' => $negocioId, 'nombre' => 'Fundas', 'icono' => 'category', 'activo' => true,
        ]);
        Producto::create([
            'negocio_id' => $negocioId, 'categoria_id' => $categoria->id,
            'codigo' => 'F-001', 'nombre' => 'Funda', 'activo' => false,
        ]);

        $this->withToken($session['token'])->getJson('/api/categorias')
            ->assertOk()->assertJsonPath('0.productos_count', 1);

        $this->withToken($session['token'])->patchJson('/api/categorias/'.$categoria->id.'/estado', [
            'activo' => false,
        ])->assertUnprocessable();
        $this->assertDatabaseHas('categorias', ['id' => $categoria->id, 'activo' => true]);

        $this->withToken($session['token'])->putJson('/api/categorias/'.$categoria->id, [
            'nombre' => 'Fundas editadas', 'icono' => 'category', 'activo' => false,
        ])->assertOk()->assertJsonPath('activo', true);
        $this->assertDatabaseHas('categorias', ['id' => $categoria->id, 'activo' => true]);

        $vacia = Categoria::create([
            'negocio_id' => $negocioId, 'nombre' => 'Cargadores', 'icono' => 'category', 'activo' => true,
        ]);
        $this->withToken($session['token'])->patchJson('/api/categorias/'.$vacia->id.'/estado', [
            'activo' => false,
        ])->assertOk()->assertJsonPath('activo', false);
    }

    public function test_products_cannot_be_assigned_to_an_inactive_category(): void
    {
        $session = $this->register('Mega Fundas', 'mega@test.local');
        $negocioId = $session['usuario']['negocio_id'];
        $inactiva = Categoria::create([
            'negocio_id' => $negocioId, 'nombre' => 'Inactiva', 'icono' => 'category', 'activo' => false,
        ]);
        $activa = Categoria::create([
            'negocio_id' => $negocioId, 'nombre' => 'Activa', 'icono' => 'category', 'activo' => true,
        ]);
        $datos = [
            'categoria_id' => $inactiva->id, 'codigo' => 'PR-1',
            'nombre' => 'Producto nuevo', 'precio_venta' => 10,
        ];

        $this->withToken($session['token'])->postJson('/api/productos', $datos)
            ->assertUnprocessable()->assertJsonValidationErrors('categoria_id');

        $producto = $this->withToken($session['token'])->postJson('/api/productos', [
            ...$datos, 'categoria_id' => $activa->id,
        ])->assertCreated()->json();
        $this->withToken($session['token'])->putJson('/api/productos/'.$producto['id'], $datos)
            ->assertUnprocessable()->assertJsonValidationErrors('categoria_id');
        $this->assertDatabaseHas('productos', [
            'id' => $producto['id'], 'categoria_id' => $activa->id,
        ]);
    }

    public function test_users_and_roles_are_isolated_by_business(): void
    {
        $mega = $this->register('Mega Fundas', 'mega@test.local');
        $other = $this->register('Otra Empresa', 'otra@test.local');

        $this->withToken($mega['token'])->getJson('/api/usuarios')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.correo', 'mega@test.local');

        $foreignRole = collect($other['usuario']['roles'])->first()['id'];
        $this->withToken($mega['token'])->postJson('/api/usuarios', [
            'nombre' => 'Usuario inválido', 'correo' => 'invalid@test.local', 'password' => 'Password123!', 'roles' => [$foreignRole],
        ])->assertUnprocessable();
    }

    public function test_administrator_can_grant_purchase_utility_visibility_without_mixing_businesses(): void
    {
        $mega = $this->register('Mega Fundas', 'mega@test.local');
        $otro = $this->register('Otro negocio', 'otro@test.local');
        $this->withToken($mega['token'])->postJson('/api/tipos-utilidad', [
            'nombre' => 'Utilidad principal', 'porcentaje_general' => 25,
            'predeterminada' => true, 'activo' => true,
        ])->assertCreated();

        $cajero = collect($this->withToken($mega['token'])->getJson('/api/roles')->json())
            ->firstWhere('identificador', 'cajero');
        $this->withToken($mega['token'])->postJson('/api/usuarios', [
            'nombre' => 'Caja', 'correo' => 'caja@test.local',
            'password' => 'Password123!', 'roles' => [$cajero['id']],
        ])->assertCreated();
        $login = $this->flushHeaders()->postJson('/api/auth/login', [
            'nombre_negocio' => 'Mega Fundas', 'correo' => 'caja@test.local',
            'password' => 'Password123!',
        ])->assertOk()->json();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->withToken($login['token'])
            ->getJson('/api/tipos-utilidad-compras')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->withToken($mega['token'])
            ->putJson('/api/roles/'.$cajero['id'].'/permisos', [
                'permisos' => ['utilidades.ver_en_compras'],
            ])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->withToken($login['token'])
            ->getJson('/api/tipos-utilidad-compras')
            ->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.porcentaje_general', '25.00');

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->withToken($otro['token'])
            ->getJson('/api/tipos-utilidad-compras')->assertOk()->assertJsonCount(0);
    }

    public function test_a_user_without_permission_is_rejected_by_the_api(): void
    {
        $session = $this->register('Mega Fundas', 'mega@test.local');
        $token = $session['token'];
        $cashierRole = $this->withToken($token)->getJson('/api/roles')->json();
        $cashierId = collect($cashierRole)->firstWhere('identificador', 'cajero')['id'];

        $this->withToken($token)->postJson('/api/usuarios', [
            'nombre' => 'Caja', 'correo' => 'caja@test.local', 'password' => 'Password123!', 'roles' => [$cashierId],
        ])->assertCreated();
        $login = $this->flushHeaders()->postJson('/api/auth/login', [
            'nombre_negocio' => 'Mega Fundas', 'correo' => 'caja@test.local',
            'password' => 'Password123!',
        ])
            ->assertOk()->assertJsonPath('usuario.correo', 'caja@test.local');
        $cashierToken = $login->json('token');

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->withToken($cashierToken)->getJson('/api/auth/me')->assertOk()->assertJsonCount(0, 'permisos');
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->withToken($cashierToken)->getJson('/api/usuarios')->assertForbidden();
    }

    public function test_an_administrator_can_update_and_deactivate_a_user_but_not_itself(): void
    {
        $session = $this->register('Mega Fundas', 'mega@test.local');
        $token = $session['token'];
        $roleId = collect($this->withToken($token)->getJson('/api/roles')->json())->first()['id'];

        $usuario = $this->withToken($token)->postJson('/api/usuarios', [
            'nombre' => 'Usuario inicial', 'correo' => 'usuario@test.local',
            'password' => 'Password123!', 'roles' => [$roleId],
        ])->assertCreated()->json();

        $this->withToken($token)->putJson('/api/usuarios/'.$usuario['id'], [
            'nombre' => 'Usuario editado', 'correo' => 'editado@test.local',
            'password' => '', 'roles' => [$roleId], 'activo' => true,
        ])->assertOk()->assertJsonPath('nombre', 'Usuario editado');

        $this->withToken($token)->deleteJson('/api/usuarios/'.$usuario['id'])->assertOk();
        $this->assertDatabaseHas('usuarios', ['id' => $usuario['id'], 'activo' => false]);

        $this->withToken($token)->deleteJson('/api/usuarios/'.$session['usuario']['id'])
            ->assertUnprocessable();
    }

    public function test_an_administrator_can_assign_permissions_to_the_cashier_role(): void
    {
        $session = $this->register('Mega Fundas', 'mega@test.local');
        $token = $session['token'];
        $cajero = collect($this->withToken($token)->getJson('/api/roles')->json())
            ->firstWhere('identificador', 'cajero');

        $this->withToken($token)->putJson('/api/roles/'.$cajero['id'].'/permisos', [
            'permisos' => ['ventas.ver', 'ventas.crear', 'productos.ver'],
        ])->assertOk();

        $this->withToken($token)->postJson('/api/usuarios', [
            'nombre' => 'Caja Principal', 'correo' => 'caja@test.local',
            'password' => 'Password123!', 'roles' => [$cajero['id']],
        ])->assertCreated();

        $login = $this->flushHeaders()->postJson('/api/auth/login', [
            'nombre_negocio' => 'Mega Fundas', 'correo' => 'caja@test.local',
            'password' => 'Password123!',
        ])->assertOk();

        $this->assertEqualsCanonicalizing(
            ['ventas.ver', 'ventas.crear', 'productos.ver'],
            $login->json('permisos'),
        );
        $this->withToken($login->json('token'))->getJson('/api/ventas')->assertOk();
    }

    public function test_permissions_can_be_adjusted_for_an_individual_user(): void
    {
        $session = $this->register('Mega Fundas', 'mega@test.local');
        $token = $session['token'];
        $cajero = collect($this->withToken($token)->getJson('/api/roles')->json())
            ->firstWhere('identificador', 'cajero');

        $this->withToken($token)->putJson('/api/roles/'.$cajero['id'].'/permisos', [
            'permisos' => ['ventas.ver', 'ventas.crear'],
        ])->assertOk();
        $usuario = $this->withToken($token)->postJson('/api/usuarios', [
            'nombre' => 'Caja Restringida', 'correo' => 'caja@test.local',
            'password' => 'Password123!', 'roles' => [$cajero['id']],
        ])->assertCreated()->json();

        $this->withToken($token)->putJson('/api/usuarios/'.$usuario['id'].'/permisos', [
            'permisos' => ['ventas.ver', 'productos.ver'],
        ])->assertOk()->assertJsonPath('permisos.0', 'ventas.ver');

        $permisos = $this->withToken($token)
            ->getJson('/api/usuarios/'.$usuario['id'].'/permisos')
            ->assertOk()
            ->json('permisos');
        $this->assertEqualsCanonicalizing(['ventas.ver', 'productos.ver'], $permisos);
    }

    private function register(string $negocio, string $correo): array
    {
        return $this->postJson('/api/auth/register', [
            'nombre_negocio' => $negocio, 'tipo_negocio' => 'Comercio', 'nombre' => 'Admin', 'correo' => $correo,
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertCreated()->json();
    }
}
