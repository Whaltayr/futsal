<?php
// auth_check.php - proteção de endpoints
// Colocar em: /var/www/html/futsal-pj/api/auth_check.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    // cookie params seguros (assumir HTTPS em produção)
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

$timeout_seconds = 1800; // 30 min inactivity
if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity'] > $timeout_seconds)) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
    }
    session_destroy();
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'Sessão expirada. Faça login.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'Não autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// permissões: admin ou manager
if (!in_array($_SESSION['role'] ?? '', ['admin','manager'], true)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'Acesso negado.'], JSON_UNESCAPED_UNICODE);
    exit;
}
