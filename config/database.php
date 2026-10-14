<?php
declare(strict_types=1);

// Devuelve la configuración de la BD
return [
    'host'     => getenv('DB_HOST') ?: '127.0.0.1',
    'port'     => getenv('DB_PORT') ?: '3306',      
    'name'     => getenv('DB_NAME') ?: 'gestion_reservas',
    'user'     => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '', 
    'charset'  => 'utf8mb4',
];