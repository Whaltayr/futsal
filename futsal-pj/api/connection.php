<?php
// connection.php - produção
// Colocar em: /var/www/html/futsal-pj/api/connection.php
// Requisitos: defina as env vars DB_HOST, DB_USER, DB_PASS, DB_NAME no servidor.

declare(strict_types=1);

$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'futsal';

function get_mysqli(): mysqli {
    static $mysqli = null;
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

    if ($mysqli !== null) return $mysqli;

    // desativa display_errors — produz em produção
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');

    $mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($mysqli->connect_errno) {
        error_log('DB connect error: ' . $mysqli->connect_error);
        // lançar exceção controlada
        throw new RuntimeException('Database connection failed');
    }

    // Charset
    if (!$mysqli->set_charset('utf8mb4')) {
        error_log('Error setting charset: ' . $mysqli->error);
    }

    // recomendável: timeouts curtos para produção
    $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

    return $mysqli;
}
