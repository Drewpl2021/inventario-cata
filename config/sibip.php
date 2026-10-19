<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Administrador inicial
    |--------------------------------------------------------------------------
    |
    | Cuenta que crea `php artisan db:seed` en un servidor nuevo. La contraseña
    | es temporal: el sistema obliga a cambiarla en el primer ingreso. Después
    | de crearla, quite SIBIP_ADMIN_PASSWORD del .env.
    |
    */

    'admin_inicial' => [
        'email' => env('SIBIP_ADMIN_EMAIL'),
        'password' => env('SIBIP_ADMIN_PASSWORD'),
        'nombre' => env('SIBIP_ADMIN_NOMBRE', 'Administrador'),
        'apellido' => env('SIBIP_ADMIN_APELLIDO', 'SIBIP'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reportes
    |--------------------------------------------------------------------------
    |
    | Por encima de este número de registros, el Excel se genera en la cola y se
    | avisa al usuario cuando está listo. Requiere un worker de cola en marcha.
    |
    */

    'reportes' => [
        'limite_excel_directo' => (int) env('SIBIP_LIMITE_EXCEL_DIRECTO', 1000),
        'filas_por_pagina' => 25,
    ],

    /*
    |--------------------------------------------------------------------------
    | Respaldos (php artisan sibip:respaldar)
    |--------------------------------------------------------------------------
    |
    | Copia diaria de la base (mysqldump comprimido) y de los archivos subidos
    | (storage/app/private). Se conservan los últimos `dias` días. Si mysqldump
    | no está en el PATH (Windows, algunos hostings), indique la ruta completa.
    |
    */

    'respaldos' => [
        'ruta' => env('SIBIP_RESPALDOS_RUTA', storage_path('app/respaldos')),
        'dias' => (int) env('SIBIP_RESPALDOS_DIAS', 14),
        'mysqldump' => env('SIBIP_MYSQLDUMP', 'mysqldump'),
        'hora' => env('SIBIP_RESPALDOS_HORA', '02:30'),
    ],

];
