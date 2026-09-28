<?php

namespace Tests\Feature;

use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegimenGeneralTest extends TestCase
{
    use RefreshDatabase;

    public function test_calcula_impuestos_independientes_y_bloquea_un_periodo_cerrado(): void
    {
        $this->seed(PermisoSeeder::class);
        $sesion = $this->postJson('/api/auth/register', ['nombre_negocio' => 'Negocio tributario', 'tipo_negocio' => 'Comercio', 'nombre' => 'Admin', 'correo' => 'tributos@test.local', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])->assertCreated()->json();
        $token = $sesion['token'];

        $this->withToken($token)->putJson('/api/impuestos/configuracion', ['iva_habilitado' => true, 'it_habilitado' => true, 'iue_habilitado' => true])->assertOk();
        $producto = $this->withToken($token)->postJson('/api/productos', ['codigo' => 'P-IVA', 'nombre' => 'Producto', 'precio_venta' => 0])->assertCreated()->json();
        $compra = ['fecha' => '2026-08-11', 'con_factura' => true, 'numero_factura' => '100', 'proveedor_nit' => '123', 'cuf_autorizacion' => 'CUF-1', 'detalles' => [['producto_id' => $producto['id'], 'cantidad' => 10, 'costo_unitario' => 50]]];
        $this->withToken($token)->postJson('/api/compras', $compra)->assertCreated()->assertJsonPath('credito_fiscal_iva', 65);
        $this->withToken($token)->postJson('/api/ventas', ['fecha' => '2026-08-11', 'con_factura' => true, 'numero_factura' => 'V-200', 'detalles' => [['producto_id' => $producto['id'], 'cantidad' => 2, 'precio_unitario' => 100]]])->assertCreated()->assertJsonPath('debito_fiscal_iva', 26)->assertJsonPath('impuesto_transacciones', 6);

        $this->withToken($token)->getJson('/api/impuestos/resumen?gestion=2026&mes=8')->assertOk()->assertJsonPath('iva_credito_compras', 65)->assertJsonPath('iva_credito_gastos', 0)->assertJsonPath('iva_saldo_favor', 39)->assertJsonPath('it_determinado', 6);
        $this->withToken($token)->getJson('/api/impuestos/productos?gestion=2026&mes=8')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.producto_id', $producto['id'])
            ->assertJsonPath('0.debito_fiscal_iva', 26)
            ->assertJsonPath('0.credito_fiscal_iva', 65)
            ->assertJsonPath('0.it_generado', 6);
        $this->withToken($token)->postJson('/api/impuestos/cerrar', ['gestion' => 2026, 'mes' => 8])->assertOk()->assertJsonPath('periodo_cerrado', true);
        $this->withToken($token)->postJson('/api/compras', $compra)->assertUnprocessable();
        $this->withToken($token)->getJson('/api/impuestos/iue?gestion=2026')->assertOk()->assertJsonPath('iue_estimado', 23.5);
    }
}
