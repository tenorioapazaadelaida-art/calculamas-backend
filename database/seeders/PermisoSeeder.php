<?php

namespace Database\Seeders;

use App\Models\Permiso;
use Illuminate\Database\Seeder;

class PermisoSeeder extends Seeder
{
    public function run(): void
    {
        $modulos = ['dashboard' => ['ver'], 'usuarios' => ['ver', 'crear', 'editar', 'desactivar'], 'roles' => ['ver', 'crear', 'editar', 'desactivar', 'asignar_permisos'], 'categorias' => ['ver', 'crear', 'editar', 'desactivar'], 'productos' => ['ver', 'crear', 'editar', 'desactivar'], 'compras' => ['ver', 'crear', 'editar', 'anular'], 'inventario' => ['ver', 'ajustar'], 'kardex' => ['ver'], 'ventas' => ['ver', 'crear', 'editar', 'anular', 'devolver'], 'utilidades' => ['ver', 'crear', 'editar', 'desactivar', 'ver_en_compras'], 'categorias_gastos' => ['ver', 'crear', 'editar', 'desactivar'], 'gastos' => ['ver', 'crear', 'editar', 'anular'], 'impuestos' => ['ver', 'configurar'], 'reportes' => ['ver', 'exportar'], 'configuracion' => ['ver', 'editar']];
        foreach ($modulos as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                Permiso::updateOrCreate(['identificador' => "$modulo.$accion"], ['nombre' => ucfirst(str_replace('_', ' ', $accion)).' '.ucfirst(str_replace('_', ' ', $modulo)), 'modulo' => $modulo, 'accion' => $accion]);
            }
        }
        Permiso::where('identificador', 'utilidades.ver_en_compras')
            ->update(['nombre' => 'Ver utilidad asignada en compras']);
    }
}
