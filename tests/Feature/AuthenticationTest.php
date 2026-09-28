<?php

namespace Tests\Feature;

use App\Models\Permiso;
use App\Models\Usuario;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisoSeeder::class);
    }

    public function test_a_business_can_register_with_an_administrator(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'nombre_negocio' => 'Mega Fundas',
            'tipo_negocio' => 'Accesorios para celulares',
            'impuestos_habilitados' => false,
            'nombre' => 'Administrador Mega Fundas',
            'correo' => 'admin@megafundas.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()->assertJsonPath('usuario.negocio.nombre', 'Mega Fundas')->assertJsonStructure(['token', 'permisos']);
        $this->assertDatabaseHas('roles', ['nombre' => 'Administrador']);
        $this->assertCount(Permiso::count(), $response->json('permisos'));
    }

    public function test_business_activity_is_required_when_registering(): void
    {
        $datos = [
            'nombre_negocio' => 'Nuevo negocio',
            'nombre' => 'Administradora',
            'correo' => 'sin-actividad@prueba.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $this->postJson('/api/auth/register', $datos)
            ->assertUnprocessable()->assertJsonValidationErrors('tipo_negocio');
        $this->postJson('/api/auth/register', [...$datos, 'tipo_negocio' => '   '])
            ->assertUnprocessable()->assertJsonValidationErrors('tipo_negocio');
        $this->assertDatabaseMissing('negocios', ['nombre' => 'Nuevo negocio']);
    }

    public function test_an_administrator_always_has_every_system_permission(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'nombre_negocio' => 'Otro Negocio',
            'tipo_negocio' => 'Comercio',
            'nombre' => 'Administradora',
            'correo' => 'administradora@otro-negocio.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated();

        $usuario = Usuario::where('correo', 'administradora@otro-negocio.test')->firstOrFail();
        $usuario->roles()->firstOrFail()->permisos()->detach();
        $usuario->permisosDirectos()->sync([
            Permiso::firstOrFail()->id => ['permitido' => false],
        ]);

        $this->assertCount(Permiso::count(), $usuario->permisos());
        $this->withToken($response->json('token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonCount(Permiso::count(), 'permisos');
    }

    public function test_inactive_users_cannot_log_in(): void
    {
        $this->postJson('/api/auth/register', [
            'nombre_negocio' => 'Mega Fundas', 'tipo_negocio' => 'Comercio', 'nombre' => 'Admin', 'correo' => 'admin@megafundas.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertCreated();

        Usuario::where('correo', 'admin@megafundas.test')->update(['activo' => false]);
        $this->postJson('/api/auth/login', [
            'nombre_negocio' => 'Mega Fundas',
            'correo' => 'admin@megafundas.test',
            'password' => 'Password123!',
        ])->assertForbidden();
    }

    public function test_user_must_log_in_with_the_correct_business_name(): void
    {
        $this->postJson('/api/auth/register', [
            'nombre_negocio' => 'Mega Fundas',
            'tipo_negocio' => 'Comercio',
            'nombre' => 'Admin',
            'correo' => 'admin@megafundas.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated();

        $this->postJson('/api/auth/login', [
            'nombre_negocio' => 'Negocio incorrecto',
            'correo' => 'admin@megafundas.test',
            'password' => 'Password123!',
        ])->assertUnprocessable();

        $this->postJson('/api/auth/login', [
            'nombre_negocio' => 'Mega Fundas',
            'correo' => 'admin@megafundas.test',
            'password' => 'Password123!',
        ])->assertOk()->assertJsonPath('usuario.negocio.nombre', 'Mega Fundas');
    }

    public function test_private_profile_requires_a_token(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_tokens_are_associated_with_the_usuario_model(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'nombre_negocio' => 'Mega Fundas', 'tipo_negocio' => 'Comercio', 'nombre' => 'Admin', 'correo' => 'admin@megafundas.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertCreated();

        $plainTextToken = $response->json('token');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => Usuario::class,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => 'App\\Models\\User',
        ]);

        $this->withToken($plainTextToken)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('usuario.correo', 'admin@megafundas.test');
    }

    public function test_legacy_token_references_are_migrated_to_usuario(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'nombre_negocio' => 'Mega Fundas', 'tipo_negocio' => 'Comercio', 'nombre' => 'Admin', 'correo' => 'admin@megafundas.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertCreated();

        $plainTextToken = $response->json('token');
        $tokenId = explode('|', $plainTextToken, 2)[0];
        DB::table('personal_access_tokens')->where('id', $tokenId)->update([
            'tokenable_type' => 'App\\Models\\User',
        ]);

        $migration = require database_path('migrations/2026_08_10_000004_update_legacy_sanctum_tokenable_type.php');
        $migration->up();

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenId,
            'tokenable_type' => Usuario::class,
        ]);
        $this->withToken($plainTextToken)->getJson('/api/auth/me')->assertOk();
    }
}
