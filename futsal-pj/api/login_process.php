<?php
// api/login_process.php
// Endpoint que aceita POST {username, password} e retorna JSON.

declare(strict_types=1);

// MOSTRAR ERROS EM DESENVOLVIMENTO (apenas enquanto estiver aprendendo)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Config do MySQLi para lançar exceções em erros de DB
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Este endpoint SEMPRE retorna JSON
header('Content-Type: application/json; charset=utf-8');

// Importa a conexão ($mysqli). Você vai criar/editar o connection.php no Passo 3
require_once __DIR__ . '/connection.php';

// Só aceita POST. Se vier GET, PUT, etc -> 405
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido. Use POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Pega dados do formulário
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validação simples
if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['erro' => 'Username e palavra-passe são obrigatórios.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Consulta o utilizador
    $stmt = $mysqli->prepare("SELECT id, username, password_hash, adrole FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // Verifica credenciais
    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['erro' => 'Credenciais inválidas.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

  session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'secure' => true,   // only with HTTPS
  'httponly' => true,
  'samesite' => 'Lax'
]);

    session_start();
    session_regenerate_id(true);

    // Guarda dados mínimos na sessão
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['adrole'];

    echo json_encode([
        'ok' => true,
        'mensagem' => 'Login bem-sucedido',
        'redirect' => '/ad_panel.html'
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => 'Erro no servidor',
        'detalhe' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}