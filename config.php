<?php

// Este archivo usa la sintaxis sencilla CLAVE="valor" de INI.
$config = parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW);

if ($config === false) {
    throw new RuntimeException('No se pudo leer el archivo .env');
}

foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $clave) {
    if (!array_key_exists($clave, $config)) {
        throw new RuntimeException('Falta configurar ' . $clave . ' en .env');
    }
}

return new PDO(
    "mysql:host={$config['DB_HOST']};dbname={$config['DB_NAME']};charset=utf8mb4",
    $config['DB_USER'],
    $config['DB_PASSWORD'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
