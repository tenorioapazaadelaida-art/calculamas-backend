<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaGasto extends Model
{
    protected $table = 'categorias_gastos';

    protected $fillable = ['negocio_id', 'nombre', 'descripcion', 'deducible_iue', 'activo'];
}
