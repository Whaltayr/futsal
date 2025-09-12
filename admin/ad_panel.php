<?php
require_once __DIR__ . '/../new-api/auth_check.php';
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Painel Admin</title>
  <link rel="stylesheet" href="/futsal-pj/assets/css/ad_painel.css">
</head>
<body
  data-base="/futsal-pj"
  data-api="/futsal-pj/new-api"
  data-partials="/futsal-pj/admin/partials"
>
  <h1>Painel Administrativo</h1>
<nav class="admin-tabs">
  <button data-tab="tournaments">Campeonatos</button>
  <button data-tab="phases">Fases</button>
  <button data-tab="teams">Equipas</button>
  <button data-tab="players">Jogadores</button>
  <button data-tab="staff">Staff</button>
  <button data-tab="matches">Jogos</button>
  <button data-tab="results">Resultados</button>
</nav>

  <main id="panelContent">
    <div id="loading" style="display:none">Carregando...</div>
  </main>

  <script src="/futsal-pj/assets/js/admin.js" defer></script>
</body>
</html>