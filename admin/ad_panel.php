<?php
require_once __DIR__ . '/../new-api/auth_check.php';
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Painel Admin</title>
  <link rel="stylesheet" href="/futsal-pj/assets/css/admin.css">
</head>
<body>
  <h1>Painel Administrativo</h1>
  <nav>
    <button data-tab="tournaments">Campeonatos</button>
    <button data-tab="teams">Equipas</button>
  </nav>

  <main id="panelContent">
    <div id="loading">Carregando...</div>
  </main>

  <script src="/futsal-pj/assets/js/admin.js" defer></script>
</body>
</html>
