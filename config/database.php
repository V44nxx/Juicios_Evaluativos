<?php

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'juicios_evaluativos');

function getConnection() {
    $conn = new mysqli(
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME,
        (int) DB_PORT
    );

    if ($conn->connect_error) {
        die(json_encode([
            'error' => 'Conexión fallida: ' . $conn->connect_error
        ], JSON_UNESCAPED_UNICODE));
    }

    $conn->set_charset('utf8mb4');

    return $conn;
}
?>