<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GastosPeriodoTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_gasto_identifica_periodos_diario_mensual_anual_y_unico(): void
    {
        $this->seed(PermisoSeeder::class);
        $sesion = $this->postJson('/api/auth/register', ['nombre_negocio' => 'Negocio gastos', 'tipo_negocio' => 'Comercio', 'nombre' => 'Admin', 'correo' => 'gastos@test.local', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])->assertCreated()->json();
        $token = $sesion['token'];
        $categoria = $this->withToken($token)->postJson('/api/categorias-gastos', ['nombre' => 'Servicios'])->assertCreated()->json();

        foreach ([
            ['diario', '2026-08-11', '2026-08-11', '2026-08-11'],
            ['mensual', '2026-08-01', '2026-08-01', '2026-08-31'],
            ['anual', '2026-01-01', '2026-01-01', '2026-12-31'],
            ['unico', null, '2026-08-11', '2026-08-11'],
        ] as [$periodicidad, $referencia, $desde, $hasta]) {
            $respuesta = $this->withToken($token)->postJson('/api/gastos', [
                'categoria_gasto_id' => $categoria['id'],
                'fecha' => '2026-08-11',
                'concepto' => "Pago {$periodicidad}",
                'periodicidad' => $periodicidad,
                'periodo_referencia' => $referencia,
                'monto' => 100,
            ])->assertCreated();

            $respuesta->assertJsonPath('periodicidad', $periodicidad)
                ->assertJsonPath('periodo_desde', Carbon::parse($desde, 'America/La_Paz')->utc()->toJSON())
                ->assertJsonPath('periodo_hasta', Carbon::parse($hasta, 'America/La_Paz')->utc()->toJSON());
        }
    }
}
