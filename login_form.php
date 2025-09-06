<?php
// login.php — simples e direto (form + processamento no mesmo ficheiro)

session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'secure' => true,   // only with HTTPS
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_start();



// Mostrar erros em desenvolvimento (remova em produção)
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

// Configuração de DB
// Importa a conexão ($mysqli). Você vai criar/editar o connection.php no Passo 3
require_once __DIR__ . '/api/connection.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Preencha o nome de utilizador e a palavra-passe.';
    } else {
        try {
    // use centralized connection (connection.php must define get_mysqli())
    $mysqli = get_mysqli();

    // normalize role name in the query (adrole -> role) so PHP uses user['role']
    $stmt = $mysqli->prepare('SELECT id, username, password_hash, adrole FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Login OK -> set session
        // If you want to customize cookie flags (secure/httponly), call session_set_cookie_params() BEFORE session_start().
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['adrole'] ?? null;

        // close statement and optionally the connection
        $stmt->close();
        // $mysqli->close(); // optional, PHP will close at end of request

        // Redirect to your admin panel — adjust path if needed
        header('Location: admin/ad_panel.php');
        exit;
    } else {
        $stmt->close();
        $error = 'Credenciais inválidas.';
    }
} catch (Throwable $e) {
    // Development: show the error. In production, log it and show a friendly message.
    $error = 'Erro no servidor: ' . $e->getMessage();
}

    }
}

// Se já estiver logado, pode redirecionar diretamente
if (!empty($_SESSION['user_id'])) {
    header('Location: ad_panel.php');
    exit;
}
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="assets/css/login.css">
  <title>Entrar — Painel Administrativo</title>
  <style>
    .toast { margin-top: 10px; color: #b00; }
    .toast.ok { color: #060; }
  </style>
</head>
<body>
  <main class="card" role="main" aria-labelledby="h1">
    <h1 id="h1">Entrar no Painel</h1>
    <?php if ($error): ?>
      <div class="toast"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <form method="post" novalidate>
      <div class="input__group">
        <label for="username">Nome de utilizador</label>
        <input id="username" name="username" type="text" autocomplete="username" required>
      </div>
      <div class="input__group">
        <label for="password">Palavra-passe</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
      </div>
      <button type="submit">Entrar</button>
    </form>
  </main>
</body>
</html> 