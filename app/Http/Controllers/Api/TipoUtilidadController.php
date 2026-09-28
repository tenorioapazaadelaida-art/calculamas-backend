<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoUtilidad;
use App\Services\ServicioAuditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TipoUtilidadController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        $this->asegurarAdministrador($r);

        return response()->json($this->consulta($r)->get());
    }

    public function opciones(Request $r): JsonResponse
    {
        return response()->json($this->consulta($r)->where('activo', true)->get());
    }

    public function opcionesCompra(Request $r): JsonResponse
    {
        return response()->json($this->consulta($r)->where('activo', true)->get());
    }

    public function store(Request $r): JsonResponse
    {
        $this->asegurarAdministrador($r);
        $d = $this->validar($r);

        $tipo = DB::transaction(
            fn () => $this->guardar(new TipoUtilidad, $d, $r),
        );
        app(ServicioAuditoria::class)->registrar(
            'crear',
            'tipos_utilidad',
            $tipo->id,
            $r->user()->negocio_id,
            [],
            $this->datosAuditoria($tipo),
        );

        return response()->json($tipo, 201);
    }

    public function update(Request $r, int $id): JsonResponse
    {
        $this->asegurarAdministrador($r);
        $d = $this->validar($r, $id);
        $tipoActual = $this->buscar($r, $id)->load('productos:id,nombre,codigo');
        $anteriores = $this->datosAuditoria($tipoActual);

        $tipo = DB::transaction(
            fn () => $this->guardar($tipoActual, $d, $r),
        );
        app(ServicioAuditoria::class)->registrar(
            'editar',
            'tipos_utilidad',
            $tipo->id,
            $r->user()->negocio_id,
            $anteriores,
            $this->datosAuditoria($tipo),
        );

        return response()->json($tipo);
    }

    public function estado(Request $r, int $id): JsonResponse
    {
        $this->asegurarAdministrador($r);
        $d = $r->validate(['activo' => ['required', 'boolean']]);
        $t = $this->buscar($r, $id);
        $anteriores = $this->datosAuditoria($t->load('productos:id,nombre,codigo'));
        $t->update($d);
        $t->load('productos:id,nombre,codigo');
        app(ServicioAuditoria::class)->registrar(
            'editar',
            'tipos_utilidad',
            $t->id,
            $r->user()->negocio_id,
            $anteriores,
            $this->datosAuditoria($t),
        );

        return response()->json($t);
    }

    private function asegurarAdministrador(Request $r): void
    {
        $esAdministrador = $r->user()
            ->roles()
            ->where('identificador', 'administrador')
            ->exists();

        abort_unless(
            $esAdministrador,
            403,
            'Solo el administrador puede gestionar los tipos de utilidad.',
        );
    }

    private function consulta(Request $r)
    {
        return TipoUtilidad::where('negocio_id', $r->user()->negocio_id)
            ->with('productos:id,nombre,codigo')
            ->orderByDesc('predeterminada')
            ->orderBy('nombre');
    }

    private function validar(Request $r, ?int $id = null): array
    {
        return $r->validate([
            'nombre' => [
                'required',
                'string',
                'max:120',
                Rule::unique('tipos_utilidad')
                    ->where('negocio_id', $r->user()->negocio_id)
                    ->ignore($id),
            ],
            'porcentaje_general' => ['required', 'numeric', 'min:0', 'max:1000'],
            'predeterminada' => ['sometimes', 'boolean'],
            'activo' => ['sometimes', 'boolean'],
            'productos' => ['sometimes', 'array'],
            'productos.*.producto_id' => [
                'required',
                'integer',
                Rule::exists('productos', 'id')
                    ->where('negocio_id', $r->user()->negocio_id),
            ],
            'productos.*.porcentaje' => [
                'required',
                'numeric',
                'min:0',
                'max:1000',
            ],
            'productos.*.activo' => ['sometimes', 'boolean'],
        ]);
    }

    private function guardar(TipoUtilidad $t, array $d, Request $r): TipoUtilidad
    {
        $ps = $d['productos'] ?? [];
        unset($d['productos']);
        if ($d['predeterminada'] ?? false) {
            TipoUtilidad::where('negocio_id', $r->user()->negocio_id)
                ->update(['predeterminada' => false]);
        }

        $d['nombre'] = ucfirst(trim($d['nombre']));
        $t->fill($d + [
            'negocio_id' => $r->user()->negocio_id,
            'usuario_registro_id' => $r->user()->id,
        ])->save();

        $productos = collect($ps)->mapWithKeys(
            fn ($producto) => [
                $producto['producto_id'] => [
                    'porcentaje' => $producto['porcentaje'],
                    'activo' => $producto['activo'] ?? true,
                ],
            ],
        );

        $t->productos()->sync($productos->all());

        return $t->load('productos:id,nombre,codigo');
    }

    private function datosAuditoria(TipoUtilidad $tipo): array
    {
        return [
            'nombre' => $tipo->nombre,
            'porcentaje_general' => $tipo->porcentaje_general,
            'predeterminada' => $tipo->predeterminada,
            'activo' => $tipo->activo,
            'productos' => $tipo->productos->map(fn ($producto) => [
                'producto' => $producto->nombre,
                'codigo' => $producto->codigo,
                'porcentaje' => $producto->pivot->porcentaje,
                'activo' => (bool) $producto->pivot->activo,
            ])->values()->all(),
        ];
    }

    private function buscar(Request $r, int $id): TipoUtilidad
    {
        return TipoUtilidad::where(
            'negocio_id',
            $r->user()->negocio_id,
        )->findOrFail($id);
    }
}
