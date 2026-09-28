<?php

namespace Tests\Feature;

use App\Models\Producto;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KardexPromedioPonderadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_kardex_general_se_puede_descargar_en_pdf(): void
    {
        $this->seed(PermisoSeeder::class);
        $sesion = $this->postJson('/api/auth/register', ['nombre_negocio' => 'Negocio PDF', 'tipo_negocio' => 'Comercio', 'nombre' => 'Admin', 'correo' => 'pdf@prueba.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])->json();
        $token = $sesion['token'];
        $producto = $this->withToken($token)->postJson('/api/productos', ['codigo' => 'PDF-1', 'nombre' => 'Producto PDF', 'precio_venta' => 30])->assertCreated()->json();
        $compra = $this->withToken($token)->postJson('/api/compras', ['fecha' => '2026-08-11', 'detalles' => [['producto_id' => $producto['id'], 'cantidad' => 2, 'costo_unitario' => 10]]])->assertCreated()->json();
        $venta = $this->withToken($token)->postJson('/api/ventas', ['fecha' => '2026-08-11', 'detalles' => [['producto_id' => $producto['id'], 'cantidad' => 1, 'precio_unitario' => 30]]])->assertCreated()->json();

        $this->withToken($token)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('stock_total', 1)
            ->assertJsonPath('ventas_total', 30)
            ->assertJsonPath('ventas', 1);

        $respuesta = $this->withToken($token)->get('/api/kardex/pdf');

        $respuesta->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
        $this->assertStringContainsString('kardex-general-', $respuesta->headers->get('content-disposition'));
        foreach (["/api/compras/{$compra['id']}/comprobante-pdf", "/api/ventas/{$venta['id']}/comprobante-pdf"] as $ruta) {
            $pdf = $this->withToken($token)->get($ruta);
            $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $pdf->getContent());
        }
    }

    public function test_compras_y_ventas_actualizan_kardex_por_promedio_ponderado(): void
    {
        $this->seed(PermisoSeeder::class);
        $sesion = $this->postJson('/api/auth/register', ['nombre_negocio' => 'Negocio prueba', 'tipo_negocio' => 'Comercio', 'nombre' => 'Admin', 'correo' => 'admin@prueba.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])->json();
        $token = $sesion['token'];
        $producto = $this->withToken($token)->postJson('/api/productos', ['codigo' => 'P-1', 'nombre' => 'Producto', 'precio_venta' => 30])->assertCreated()->json();
        foreach ([[10, 10], [10, 20]] as [$cantidad,$costo]) {
            $this->withToken($token)->postJson('/api/compras', ['fecha' => '2026-08-10', 'detalles' => [['producto_id' => $producto['id'], 'cantidad' => $cantidad, 'costo_unitario' => $costo]]])->assertCreated();
        }$this->assertEquals(15, (float) Producto::find($producto['id'])->costo_promedio);
        $this->withToken($token)->postJson('/api/ventas', ['detalles' => [['producto_id' => $producto['id'], 'cantidad' => 5, 'precio_unitario' => 30]]])->assertCreated();
        $actual = Producto::find($producto['id']);
        $this->assertEquals(15, (float) $actual->stock_actual);
        $this->assertEquals(15, (float) $actual->costo_promedio);
        $this->assertDatabaseCount('movimientos_inventario', 3);
    }

    public function test_compra_por_paquete_convierte_a_unidades_y_conserva_la_presentacion(): void
    {
        $this->seed(PermisoSeeder::class);
        $sesion = $this->postJson('/api/auth/register', [
            'nombre_negocio' => 'Negocio paquetes', 'tipo_negocio' => 'Comercio', 'nombre' => 'Admin', 'correo' => 'paquetes@prueba.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertCreated()->json();
        $producto = $this->withToken($sesion['token'])->postJson('/api/productos', [
            'codigo' => 'P-PAQ', 'nombre' => 'Artículo', 'precio_venta' => 10,
        ])->assertCreated()->json();
        $detalle = [
            'producto_id' => $producto['id'], 'presentacion' => 'paquete',
            'cantidad' => 2, 'unidades_por_paquete' => 12, 'costo_unitario' => 60,
        ];

        $this->withToken($sesion['token'])->postJson('/api/compras', [
            'fecha' => '2026-08-10', 'detalles' => [[...$detalle, 'unidades_por_paquete' => 1]],
        ])->assertUnprocessable();

        $compra = $this->withToken($sesion['token'])->postJson('/api/compras', [
            'fecha' => '2026-08-10', 'detalles' => [$detalle],
        ])->assertCreated()->assertJsonPath('total', 120)
            ->assertJsonPath('detalles.0.presentacion', 'paquete')
            ->assertJsonPath('detalles.0.unidades_por_paquete', 12)
            ->json();
        $this->assertEquals(24, (float) Producto::find($producto['id'])->stock_actual);
        $this->assertEquals(5, (float) Producto::find($producto['id'])->costo_promedio);
        $this->assertEquals(24, (float) $compra['detalles'][0]['cantidad']);
        $this->assertEquals(2, (float) $compra['detalles'][0]['cantidad_presentacion']);

        $this->withToken($sesion['token'])->putJson('/api/compras/'.$compra['id'], [
            'fecha' => '2026-08-10', 'detalles' => [[...$detalle, 'cantidad' => 1]],
        ])->assertOk()->assertJsonPath('total', 60);
        $this->assertEquals(12, (float) Producto::find($producto['id'])->stock_actual);
    }

    public function test_una_compra_admite_varios_productos_y_calcula_un_solo_credito_fiscal(): void
    {
        $this->seed(PermisoSeeder::class);
        $sesion = $this->postJson('/api/auth/register', ['nombre_negocio' => 'Negocio compra múltiple', 'tipo_negocio' => 'Comercio', 'nombre' => 'Admin', 'correo' => 'multiple@prueba.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])->json();
        $token = $sesion['token'];
        $primero = $this->withToken($token)->postJson('/api/productos', ['codigo' => 'CAR-25', 'nombre' => 'Cargador 25 W', 'precio_venta' => 0])->assertCreated()->json();
        $segundo = $this->withToken($token)->postJson('/api/productos', ['codigo' => 'CAR-33', 'nombre' => 'Cargador 33 W', 'precio_venta' => 0])->assertCreated()->json();

        $respuesta = $this->withToken($token)->postJson('/api/compras', [
            'fecha' => '2026-08-10',
            'con_factura' => true,
            'detalles' => [
                ['producto_id' => $primero['id'], 'cantidad' => 5, 'costo_unitario' => 80],
                ['producto_id' => $segundo['id'], 'cantidad' => 3, 'costo_unitario' => 95],
            ],
        ])->assertCreated()->assertJsonCount(2, 'detalles')->assertJsonPath('subtotal', 685);

        $this->assertEquals(89.05, (float) $respuesta->json('credito_fiscal_iva'));
        $this->assertEquals(80, (float) Producto::find($primero['id'])->costo_promedio);
        $this->assertEquals(95, (float) Producto::find($segundo['id'])->costo_promedio);
        $this->assertDatabaseHas('detalles_compra', ['producto_id' => $primero['id'], 'costo_inventario_unitario' => 80, 'subtotal_inventario' => 400]);
        $this->withToken($token)->postJson('/api/compras', ['fecha' => '2026-08-10', 'con_factura' => false, 'detalles' => [['producto_id' => $primero['id'], 'cantidad' => 1, 'costo_unitario' => 80]]])->assertCreated()->assertJsonPath('credito_fiscal_iva', 0);
        $this->withToken($token)->postJson('/api/ventas', ['fecha' => '2026-08-10', 'con_factura' => true, 'numero_factura' => 'V-100', 'detalles' => [['producto_id' => $primero['id'], 'cantidad' => 1, 'precio_unitario' => 100]]])->assertCreated()->assertJsonPath('debito_fiscal_iva', 13);
        $this->withToken($token)->postJson('/api/ventas', ['fecha' => '2026-08-10', 'con_factura' => false, 'detalles' => [['producto_id' => $primero['id'], 'cantidad' => 1, 'precio_unitario' => 100]]])->assertCreated()->assertJsonPath('debito_fiscal_iva', 0);
        $this->withToken($token)->getJson('/api/ventas')->assertOk()->assertJsonPath('0.detalles.0.producto.nombre', 'Cargador 25 W');
        $this->assertEquals(4, (float) Producto::find($primero['id'])->stock_actual);
        $this->assertEquals(3, (float) Producto::find($segundo['id'])->stock_actual);
        $this->assertDatabaseCount('compras', 2);
        $this->assertDatabaseCount('detalles_compra', 3);
        $this->assertDatabaseCount('movimientos_inventario', 5);
    }

    public function test_compra_tiene_lectura_edicion_y_anulacion_con_reversion_de_stock(): void
    {
        $this->seed(PermisoSeeder::class);
        $sesion = $this->postJson('/api/auth/register', ['nombre_negocio' => 'Negocio CRUD', 'tipo_negocio' => 'Comercio', 'nombre' => 'Admin', 'correo' => 'crud@prueba.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])->json();
        $token = $sesion['token'];
        $producto = $this->withToken($token)->postJson('/api/productos', ['codigo' => 'CRUD-1', 'nombre' => 'Producto CRUD', 'precio_venta' => 0])->assertCreated()->json();
        $compra = $this->withToken($token)->postJson('/api/compras', ['fecha' => '2026-08-10', 'proveedor_nombre' => 'Inicial', 'con_factura' => true, 'numero_factura' => '10', 'detalles' => [['producto_id' => $producto['id'], 'cantidad' => 4, 'costo_unitario' => 25]]])->assertCreated()->assertJsonPath('numero_registro', 1)->json();

        $this->withToken($token)->getJson("/api/compras/{$compra['id']}")->assertOk()->assertJsonPath('detalles.0.producto.nombre', 'Producto CRUD');
        $this->withToken($token)->putJson("/api/compras/{$compra['id']}", [
            'fecha' => '2026-08-10',
            'proveedor_nombre' => 'Corregido',
            'con_factura' => true,
            'numero_factura' => '11',
            'observacion' => 'Dato corregido',
            'detalles' => [[
                'producto_id' => $producto['id'],
                'cantidad' => 6,
                'costo_unitario' => 30,
            ]],
        ])->assertOk()->assertJsonPath('proveedor_nombre', 'Corregido')->assertJsonPath('total', 180);
        $this->assertEquals(6, (float) Producto::find($producto['id'])->stock_actual);
        $this->assertEquals(30, (float) Producto::find($producto['id'])->costo_promedio);
        $kardexEditado = $this->withToken($token)->getJson('/api/kardex')->assertOk();
        $this->assertCount(1, collect($kardexEditado->json())->where('referencia_tipo', 'compra')->where('referencia_id', $compra['id']));
        $kardexEditado->assertJsonFragment(['entrada_cantidad' => 6]);
        $this->withToken($token)->deleteJson("/api/compras/{$compra['id']}")->assertOk()->assertJsonPath('estado', 'anulada');

        $this->assertEquals(0, (float) Producto::find($producto['id'])->stock_actual);
        $this->assertDatabaseHas('movimientos_inventario', ['referencia_id' => $compra['id'], 'tipo' => 'salida_anulacion_compra']);
        $this->assertDatabaseHas('auditorias', ['entidad' => 'compras', 'entidad_id' => $compra['id'], 'accion' => 'crear']);
        $this->assertDatabaseHas('auditorias', ['entidad' => 'compras', 'entidad_id' => $compra['id'], 'accion' => 'editar']);
        $this->assertDatabaseHas('auditorias', ['entidad' => 'compras', 'entidad_id' => $compra['id'], 'accion' => 'anular']);
        $kardex = $this->withToken($token)->getJson('/api/kardex')
            ->assertOk()
            ->assertJsonMissing(['tipo' => 'salida_anulacion_compra'])
            ->assertJsonMissing(['tipo' => 'ajuste_salida']);
        $this->assertFalse(collect($kardex->json())->contains(
            fn (array $movimiento) => $movimiento['referencia_tipo'] === 'compra'
                && $movimiento['referencia_id'] === $compra['id']
        ));
    }

    public function test_compra_con_venta_posterior_no_se_puede_editar_ni_anular(): void
    {
        $this->seed(PermisoSeeder::class);
        $sesion = $this->postJson('/api/auth/register', [
            'nombre_negocio' => 'Negocio bloqueos', 'tipo_negocio' => 'Comercio',
            'nombre' => 'Admin', 'correo' => 'bloqueos@prueba.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertCreated()->json();
        $token = $sesion['token'];
        $primero = $this->withToken($token)->postJson('/api/productos', [
            'codigo' => 'BLOQ-1', 'nombre' => 'Audífonos', 'precio_venta' => 50,
        ])->assertCreated()->json();
        $segundo = $this->withToken($token)->postJson('/api/productos', [
            'codigo' => 'BLOQ-2', 'nombre' => 'Fundas', 'precio_venta' => 30,
        ])->assertCreated()->json();
        $compra = $this->withToken($token)->postJson('/api/compras', [
            'fecha' => '2026-08-10', 'detalles' => [
                ['producto_id' => $primero['id'], 'cantidad' => 10, 'costo_unitario' => 20],
                ['producto_id' => $segundo['id'], 'cantidad' => 5, 'costo_unitario' => 10],
            ],
        ])->assertCreated()->json();
        $venta = $this->withToken($token)->postJson('/api/ventas', [
            'fecha' => '2026-08-11', 'detalles' => [
                ['producto_id' => $primero['id'], 'cantidad' => 2, 'precio_unitario' => 50],
            ],
        ])->assertCreated()->json();

        $this->withToken($token)->getJson('/api/compras')
            ->assertOk()->assertJsonPath('0.venta_posterior.producto', 'Audífonos');
        $this->withToken($token)->putJson('/api/compras/'.$compra['id'], [
            'fecha' => '2026-08-10', 'detalles' => [
                ['producto_id' => $primero['id'], 'cantidad' => 12, 'costo_unitario' => 20],
            ],
        ])->assertUnprocessable()->assertJsonPath('errors.compra.0',
            'No se puede editar ni anular esta compra: Audífonos tiene una venta posterior vigente ('.$venta['numero'].').');
        $this->withToken($token)->deleteJson('/api/compras/'.$compra['id'])->assertUnprocessable();
        $this->assertDatabaseHas('compras', ['id' => $compra['id'], 'estado' => 'confirmada']);
        $this->assertEquals(8, (float) Producto::find($primero['id'])->stock_actual);

        $nueva = $this->withToken($token)->postJson('/api/compras', [
            'fecha' => '2026-08-12', 'detalles' => [
                ['producto_id' => $primero['id'], 'cantidad' => 1, 'costo_unitario' => 20],
            ],
        ])->assertCreated()->json();
        $this->withToken($token)->getJson('/api/compras')
            ->assertOk()->assertJsonPath('0.venta_posterior', null);
        $this->withToken($token)->putJson('/api/compras/'.$nueva['id'], [
            'fecha' => '2026-08-12', 'detalles' => [
                ['producto_id' => $primero['id'], 'cantidad' => 2, 'costo_unitario' => 20],
            ],
        ])->assertOk();

        $this->withToken($token)->deleteJson('/api/ventas/'.$venta['id'])->assertOk();
        $this->withToken($token)->getJson('/api/compras')
            ->assertOk()->assertJsonPath('1.venta_posterior', null);
        $this->withToken($token)->deleteJson('/api/compras/'.$compra['id'])->assertOk();
    }

    public function test_venta_tiene_lectura_edicion_y_anulacion_con_reversion_de_stock(): void
    {
        $this->seed(PermisoSeeder::class);
        $sesion = $this->postJson('/api/auth/register', ['nombre_negocio' => 'Negocio CRUD venta', 'tipo_negocio' => 'Comercio', 'nombre' => 'Admin', 'correo' => 'crud-venta@prueba.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])->json();
        $token = $sesion['token'];
        $producto = $this->withToken($token)->postJson('/api/productos', ['codigo' => 'VENTA-1', 'nombre' => 'Producto Venta', 'precio_venta' => 50])->assertCreated()->json();
        $this->withToken($token)->postJson('/api/compras', ['fecha' => '2026-08-10', 'detalles' => [['producto_id' => $producto['id'], 'cantidad' => 10, 'costo_unitario' => 25]]])->assertCreated();
        $venta = $this->withToken($token)->postJson('/api/ventas', ['fecha' => '2026-08-10', 'cliente_nombre' => 'Cliente Inicial', 'detalles' => [['producto_id' => $producto['id'], 'cantidad' => 2, 'precio_unitario' => 50]]])->assertCreated()->json();

        $this->withToken($token)->getJson("/api/ventas/{$venta['id']}")->assertOk()->assertJsonPath('detalles.0.producto.nombre', 'Producto Venta');
        $this->withToken($token)->putJson("/api/ventas/{$venta['id']}", ['fecha' => '2026-08-10', 'cliente_nombre' => 'Cliente Corregido', 'detalles' => [['producto_id' => $producto['id'], 'cantidad' => 3, 'precio_unitario' => 55]]])->assertOk()->assertJsonPath('cliente_nombre', 'Cliente Corregido')->assertJsonPath('total', 165);
        $this->assertEquals(7, (float) Producto::find($producto['id'])->stock_actual);
        $kardexEditado = $this->withToken($token)->getJson('/api/kardex')->assertOk();
        $this->assertCount(1, collect($kardexEditado->json())->where('referencia_tipo', 'venta')->where('referencia_id', $venta['id']));
        $kardexEditado->assertJsonFragment(['salida_cantidad' => 3]);
        $this->withToken($token)->deleteJson("/api/ventas/{$venta['id']}")->assertOk()->assertJsonPath('estado', 'anulada');
        $this->assertEquals(10, (float) Producto::find($producto['id'])->stock_actual);
        $this->assertDatabaseHas('movimientos_inventario', ['referencia_id' => $venta['id'], 'tipo' => 'entrada_anulacion_venta']);
        $kardex = $this->withToken($token)->getJson('/api/kardex')
            ->assertOk()
            ->assertJsonMissing(['tipo' => 'entrada_anulacion_venta'])
            ->assertJsonMissing(['tipo' => 'ajuste_entrada']);
        $this->assertFalse(collect($kardex->json())->contains(
            fn (array $movimiento) => $movimiento['referencia_tipo'] === 'venta'
                && $movimiento['referencia_id'] === $venta['id']
        ));
    }

    public function test_venta_guarda_y_actualiza_la_forma_de_pago(): void
    {
        $this->seed(PermisoSeeder::class);
        $sesion = $this->postJson('/api/auth/register', [
            'nombre_negocio' => 'Negocio formas de pago',
            'tipo_negocio' => 'Comercio',
            'nombre' => 'Admin',
            'correo' => 'pagos@prueba.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated()->json();
        $token = $sesion['token'];
        $producto = $this->withToken($token)->postJson('/api/productos', [
            'codigo' => 'PAGO-1',
            'nombre' => 'Producto para pago',
            'precio_venta' => 50,
        ])->assertCreated()->json();

        $this->withToken($token)->postJson('/api/compras', [
            'fecha' => '2026-08-18',
            'detalles' => [[
                'producto_id' => $producto['id'],
                'cantidad' => 5,
                'costo_unitario' => 25,
            ]],
        ])->assertCreated();

        $venta = $this->withToken($token)->postJson('/api/ventas', [
            'fecha' => '2026-08-18',
            'metodo_pago' => 'qr',
            'detalles' => [[
                'producto_id' => $producto['id'],
                'cantidad' => 1,
                'precio_unitario' => 50,
            ]],
        ])->assertCreated()
            ->assertJsonPath('metodo_pago', 'qr')
            ->json();

        $this->withToken($token)->putJson("/api/ventas/{$venta['id']}", [
            'fecha' => '2026-08-18',
            'metodo_pago' => 'efectivo',
            'detalles' => [[
                'producto_id' => $producto['id'],
                'cantidad' => 1,
                'precio_unitario' => 50,
            ]],
        ])->assertOk()
            ->assertJsonPath('metodo_pago', 'efectivo');

        $this->assertDatabaseHas('ventas', [
            'id' => $venta['id'],
            'metodo_pago' => 'efectivo',
        ]);
    }

    public function test_no_permite_vender_una_cantidad_mayor_al_stock_disponible(): void
    {
        $this->seed(PermisoSeeder::class);
        $sesion = $this->postJson('/api/auth/register', [
            'nombre_negocio' => 'Negocio control stock',
            'tipo_negocio' => 'Comercio',
            'nombre' => 'Admin',
            'correo' => 'stock@prueba.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated()->json();
        $token = $sesion['token'];
        $producto = $this->withToken($token)->postJson('/api/productos', [
            'codigo' => 'STOCK-1',
            'nombre' => 'Producto limitado',
            'precio_venta' => 20,
        ])->assertCreated()->json();

        $this->withToken($token)->postJson('/api/compras', [
            'fecha' => '2026-08-14',
            'detalles' => [[
                'producto_id' => $producto['id'],
                'cantidad' => 2,
                'costo_unitario' => 10,
            ]],
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/ventas', [
            'detalles' => [[
                'producto_id' => $producto['id'],
                'cantidad' => 3,
                'precio_unitario' => 20,
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('productos');

        $this->assertDatabaseCount('ventas', 0);
        $this->assertEquals(2, (float) Producto::find($producto['id'])->stock_actual);
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_devolucion_parcial_reintegra_stock_y_no_permite_exceder_la_venta(): void
    {
        $this->seed(PermisoSeeder::class);
        $sesion = $this->postJson('/api/auth/register', [
            'nombre_negocio' => 'Negocio devoluciones', 'tipo_negocio' => 'Comercio',
            'nombre' => 'Admin', 'correo' => 'devoluciones@prueba.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertCreated()->json();
        $token = $sesion['token'];
        $producto = $this->withToken($token)->postJson('/api/productos', [
            'codigo' => 'DEV-1', 'nombre' => 'Audífonos', 'precio_venta' => 60,
        ])->assertCreated()->json();
        $this->withToken($token)->postJson('/api/compras', [
            'fecha' => '2026-09-25', 'detalles' => [[
                'producto_id' => $producto['id'], 'cantidad' => 10, 'costo_unitario' => 30,
            ]],
        ])->assertCreated();
        $venta = $this->withToken($token)->postJson('/api/ventas', [
            'fecha' => '2026-09-25', 'metodo_pago' => 'qr', 'detalles' => [[
                'producto_id' => $producto['id'], 'cantidad' => 3, 'precio_unitario' => 60,
            ]],
        ])->assertCreated()->json();

        $devolucion = $this->withToken($token)->postJson("/api/ventas/{$venta['id']}/devoluciones", [
            'motivo' => 'Producto defectuoso', 'metodo_reembolso' => 'qr',
            'detalles' => [[
                'detalle_venta_id' => $venta['detalles'][0]['id'], 'cantidad' => 1,
                'reintegrar_stock' => true,
            ]],
        ])->assertCreated()->assertJsonPath('total', 60)->json();

        $pdf = $this->withToken($token)->get("/api/ventas/{$venta['id']}/devoluciones/{$devolucion['id']}/comprobante-pdf");
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->assertEquals(8, (float) Producto::find($producto['id'])->stock_actual);
        $this->assertDatabaseHas('ventas', ['id' => $venta['id'], 'estado' => 'devuelta_parcial']);
        $this->assertDatabaseHas('movimientos_inventario', ['tipo' => 'entrada_devolucion_venta']);
        $this->withToken($token)->getJson('/api/kardex')->assertOk()
            ->assertJsonFragment(['tipo' => 'entrada_devolucion_venta']);
        $this->withToken($token)->getJson('/api/reportes/utilidad?gestion=2026&mes=9')->assertOk()
            ->assertJsonPath('ingresos', 120)->assertJsonPath('costo_ventas', 60);
        $this->withToken($token)->putJson("/api/ventas/{$venta['id']}", [
            'detalles' => [['producto_id' => $producto['id'], 'cantidad' => 2, 'precio_unitario' => 60]],
        ])->assertUnprocessable();
        $this->withToken($token)->postJson("/api/ventas/{$venta['id']}/devoluciones", [
            'motivo' => 'Exceso', 'metodo_reembolso' => 'qr',
            'detalles' => [[
                'detalle_venta_id' => $venta['detalles'][0]['id'], 'cantidad' => 3,
                'reintegrar_stock' => true,
            ]],
        ])->assertUnprocessable();
        $this->withToken($token)->postJson("/api/ventas/{$venta['id']}/devoluciones", [
            'fecha' => now('America/La_Paz')->addDay()->toDateString(),
            'motivo' => 'Fecha futura', 'metodo_reembolso' => 'qr',
            'detalles' => [[
                'detalle_venta_id' => $venta['detalles'][0]['id'], 'cantidad' => 1,
                'reintegrar_stock' => true,
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors('fecha');
    }

    public function test_devolucion_de_compra_reduce_stock_credito_fiscal_y_aparece_en_kardex(): void
    {
        $this->seed(PermisoSeeder::class);
        $sesion = $this->postJson('/api/auth/register', [
            'nombre_negocio' => 'Negocio devolución proveedor', 'tipo_negocio' => 'Comercio',
            'nombre' => 'Admin', 'correo' => 'proveedor@prueba.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertCreated()->json();
        $token = $sesion['token'];
        $producto = $this->withToken($token)->postJson('/api/productos', [
            'codigo' => 'DCP-1', 'nombre' => 'Fundas', 'precio_venta' => 20,
        ])->assertCreated()->json();
        $compra = $this->withToken($token)->postJson('/api/compras', [
            'fecha' => '2026-09-25', 'con_factura' => true, 'numero_factura' => 'F-100',
            'detalles' => [[
                'producto_id' => $producto['id'], 'cantidad' => 10, 'costo_unitario' => 10,
            ]],
        ])->assertCreated()->json();

        $devolucion = $this->withToken($token)->postJson("/api/compras/{$compra['id']}/devoluciones", [
            'fecha' => '2026-09-26', 'motivo' => 'Dos unidades defectuosas',
            'solucion' => 'reembolso', 'medio_reembolso' => 'transferencia',
            'numero_nota_credito_debito' => 'NCD-10',
            'detalles' => [['detalle_compra_id' => $compra['detalles'][0]['id'], 'cantidad' => 2]],
        ])->assertCreated()->assertJsonPath('total', 20)
            ->assertJsonPath('ajuste_credito_fiscal_iva', 2.6)->json();

        $this->assertEquals(8, (float) Producto::find($producto['id'])->stock_actual);
        $this->assertDatabaseHas('compras', ['id' => $compra['id'], 'estado' => 'devuelta_parcial']);
        $this->assertDatabaseHas('movimientos_inventario', ['tipo' => 'salida_devolucion_compra']);
        $this->withToken($token)->getJson('/api/kardex')->assertOk()
            ->assertJsonFragment(['tipo' => 'salida_devolucion_compra']);
        $this->withToken($token)->getJson('/api/impuestos/resumen?gestion=2026&mes=9')->assertOk()
            ->assertJsonPath('iva_credito_compras', 10.4);
        $this->withToken($token)->get("/api/compras/{$compra['id']}/devoluciones/{$devolucion['id']}/comprobante-pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->withToken($token)->putJson("/api/compras/{$compra['id']}", [
            'fecha' => '2026-09-25', 'detalles' => [[
                'producto_id' => $producto['id'], 'cantidad' => 10, 'costo_unitario' => 10,
            ]],
        ])->assertUnprocessable();
        $this->withToken($token)->postJson("/api/compras/{$compra['id']}/devoluciones", [
            'fecha' => '2026-09-26', 'motivo' => 'Cantidad excesiva', 'solucion' => 'reemplazo',
            'numero_nota_credito_debito' => 'NCD-11',
            'detalles' => [['detalle_compra_id' => $compra['detalles'][0]['id'], 'cantidad' => 9]],
        ])->assertUnprocessable();
    }
}
