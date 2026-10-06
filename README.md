# Sistema comercial-contable

Backend API multiempresa para pequeños negocios de Bolivia, desarrollado con Laravel 12, PHP 8.3 y MySQL.

## Arquitectura

- `app/Http/Controllers/Api`: coordinación HTTP y respuestas JSON.
- `app/Http/Requests`: autorización y validación de entradas.
- `app/Models`: entidades y relaciones Eloquent.
- `app/Services`: reglas de negocio, transacciones, Kardex, impuestos y reportes.
- `database/migrations`: esquema versionado de la base de datos.
- `database/seeders`: permisos del sistema y empresa piloto local.
- `tests/Feature`: validación de autenticación, multiempresa y Kardex.

Los controladores no contienen reglas de validación ni cálculos contables. Las compras y ventas se ejecutan dentro de transacciones de base de datos y el inventario no admite escritura directa.

## Ejecución local

```bash
php artisan migrate
php artisan serve
```

La API queda disponible en `http://127.0.0.1:8000/api`.

## Verificación

```bash
php artisan test
vendor/bin/pint --test
```

## Inventario

El Kardex utiliza promedio ponderado. Cada compra recalcula el costo promedio y cada venta conserva el costo vigente del producto vendido.

## Impuestos

Los reportes tributarios son controles internos configurables para Bolivia. Las declaraciones oficiales corresponden al SIAT del Servicio de Impuestos Nacionales.
"# bkcalculamas"  
