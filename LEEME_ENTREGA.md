# Calcula+: código para GitHub
Copia del código local actual. Extrae este ZIP y sube el contenido de la carpeta a su repositorio, incluyendo los archivos ocultos .gitignore y .env.example. No subas el ZIP como sustituto del código.
Se excluyeron historial Git, credenciales locales, respaldos, datos de negocio, dependencias instaladas y archivos generados. Los archivos de dependencias y sus lockfiles se conservan.
Instalación nueva: composer install; copiar .env.example a .env; configurar una base de datos vacía; php artisan key:generate; php artisan migrate --seed; php artisan storage:link; php artisan serve. Requiere PHP compatible con composer.lock y sus extensiones.
No se incluyeron la base de datos ni imágenes de productos del negocio. Se conservan las migraciones y el seeder de permisos. Se excluyó MegaFundasSeeder porque contiene una contraseña fija; registra el negocio desde la aplicación.
