<?php

namespace App\Services;

use App\Models\Negocio;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class RegistroNegocioService
{
    public function registrar(array $data): Usuario
    {
        return DB::transaction(function () use ($data) {
            $negocio = Negocio::create([
                'nombre' => $data['nombre_negocio'],
                'tipo_negocio' => $data['tipo_negocio'] ?? null,
                'nit' => $data['nit'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'impuestos_habilitados' => $data['impuestos_habilitados'] ?? false,
                'regimen_tributario' => $data['regimen_tributario'] ?? null,
            ]);

            $usuario = $negocio->usuarios()->create([
                'nombre' => $data['nombre'],
                'correo' => $data['correo'],
                'password' => $data['password'],
            ]);

            $administrador = Rol::create([
                'negocio_id' => $negocio->id,
                'nombre' => 'Administrador',
                'identificador' => 'administrador',
                'sistema' => true,
            ]);
            $administrador->permisos()->sync(Permiso::pluck('id'));
            $usuario->roles()->attach($administrador);

            foreach ([['Vendedor', 'vendedor'], ['Cajero', 'cajero']] as [$nombre, $identificador]) {
                Rol::create(['negocio_id' => $negocio->id, 'nombre' => $nombre, 'identificador' => $identificador, 'sistema' => true]);
            }

            return $usuario->load('negocio', 'roles');
        });
    }
}
