<?php
// /var/www/html/futsal-pj/api/logout.php
// Logout simples: suporta GET e POST, destrói sessão e redireciona para login.

// Cabeçalho opcional (não estritamente necessário para redirect)
header('Content-Type: text/plain; charset=utf-8');

// Iniciar sessão (se não iniciada)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpar sessão
$_SESSION = [];

// Remover cookie de sessão se existir
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true
    );
}

// Destruir sessão
session_destroy();

// Redirecionar para a página de login (ajusta a path se necessário)
header('Location: /futsal-pj/login_form.php');
exit;
