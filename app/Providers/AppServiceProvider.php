<?php

namespace App\Providers;

use App\Models\Compra;
use App\Models\ConfiguracionImpuesto;
use App\Models\Gasto;
use App\Models\ImpuestoGenerado;
use App\Models\Venta;
use App\Models\DevolucionVenta;
use App\Models\DevolucionCompra;
use App\Models\Categoria;
use App\Models\CategoriaGasto;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use App\Observers\AuditoriaObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([Compra::class, Venta::class, DevolucionVenta::class, DevolucionCompra::class, Gasto::class,
            Categoria::class, CategoriaGasto::class, Producto::class, Rol::class,
            Usuario::class, ConfiguracionImpuesto::class, ImpuestoGenerado::class] as $modelo) {
            $modelo::observe(AuditoriaObserver::class);
        }
    }
}
