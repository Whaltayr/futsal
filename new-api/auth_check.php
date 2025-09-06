<?php
// auth_check.php (dev)
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
if (empty($_SESSION['user_id'])) {
    // para AJAX devolve 401 JSON; para browser redireciona
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro'=>'Não autenticado']);
    } else {
        header('Location: /futsal-pj/login_form.php');
    }
    exit;
}
