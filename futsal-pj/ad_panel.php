<!-- admin_panel.html
   Layout semântico do Painel Administrativo (HTML puro, sem CSS).
   - Estilize com o seu ficheiro CSS.
   - Os elementos têm IDs e data-attributes para ligar ao JS/backend.
-->

<?php
// ad_panel.php — página protegida
// 1) Garante que só entra quem fez login
// 2) Evita cache para não “voltar” após logout
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'secure' => true,   // only with HTTPS
  'httponly' => true,
  'samesite' => 'Lax'
]);


session_start();

if (empty($_SESSION['user_id'])) {
  header('Location: login_form.php');
  exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$username = $_SESSION['username'] ?? '—';
$role     = $_SESSION['role'] ?? '—';


?>
<!doctype html>
<html lang="pt">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="assets/css/ad_painel.css">
  <title>Painel Admin — Campeonato de Futsal</title>
</head>

<body>
  <div class="layout" id="app">

    <!-- SIDEBAR -->
    <aside class="sidebar" role="navigation" aria-label="Navegação do painel">
      <h2>Futsal • Admin</h2>
      <div class="small">Utilizador: <strong id="ui-username"><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></strong></div>
      <nav class="nav" aria-label="Menu principal">
        <button class="active" data-target="dashboard">Dashboard</button>
        <button data-target="tournaments">Torneios</button>
        <button data-target="teams">Equipas</button>
        <button data-target="players">Jogadores</button>
        <button data-target="staff">Staff</button>
        <button data-target="phases">Fases & Grupos</button>
        <button data-target="fixtures">Agenda / Jogos</button>
        <button data-target="reports">Relatórios</button>
        <button data-target="settings">Configurações</button>
      </nav>

      <div style="margin-top:18px">
        <a href="/futsal-pj/api/logout.php" id="btnLogout"
          style="background:#fff;color:#022;padding:8px;border-radius:6px;border:0;cursor:pointer">Terminar
          sessão</a>
      </div>
    </aside>

    <!-- MAIN -->
    <main role="main" aria-live="polite">
      <header>
        <div>
          <h1 id="pageTitle">Painel</h1>
          <div class="small" id="pageSub">Bem-vindo — Gestão do Campeonato</div>
        </div>
        <div style="display:flex;gap:12px;align-items:center">
          <div class="small">Torneio: <strong id="ui-tournament-name">Campeonato 2025</strong></div>
          <button id="btnRefresh" title="Atualizar dados">Atualizar</button>
        </div>
      </header>

      <section class="content" id="content">

        <!-- DASHBOARD (visível por defeito) -->
        <div id="panel-dashboard" class="panel" data-role="panel">
          <h2>Dashboard</h2>
          <div class="row" style="margin-top:12px">
            <div class="card" style="flex:1">
              <h3>Resumo</h3>
              <p class="small">Jornadas programadas, próximos jogos e alertas.</p>
              <!-- placeholders para dados dinâmicos -->
              <div id="nextMatches"></div>
            </div>
            <div class="card" style="width:320px">
              <h3>Atalhos rápidos</h3>
              <div style="display:flex;flex-direction:column;gap:8px">
                <button data-action="new-tournament">Novo torneio</button>
                <button data-action="new-team">Cadastrar equipa</button>
                <button data-action="new-fixture">Agendar jogo</button>
                <button data-action="resolve-knockouts">Gerar Knockouts</button>
              </div>
            </div>
          </div>
        </div>

        <!-- TORNEIOS -->
        <div id="panel-tournaments" class="panel" data-role="panel" style="display:none">
          <h2>Torneios</h2>
          <div class="small">Crie e edite torneios.</div>
          <section id="tournaments-list" style="margin-top:12px"></section>
        </div>

        <!-- EQUIPAS -->
        <div id="panel-teams" class="panel" data-role="panel" style="display:none">
          <h2>Equipas</h2>
          <p class="small">Gerencie equipas, logos e abreviaturas.</p>
          <div style="margin-top:12px">
            <?php require_once __DIR__ . '/api/csrf.php'; ?>
            <form id="formTeam" action="/futsal-pj/api/create_team.php" method="post" enctype="multipart/form-data">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input name="name" required>
              <input name="abbreviation">
              <select name="tournament_id"></select>
              <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
              <button type="submit">Salvar</button>
            </form>


          </div>

          <div style="margin-top:18px">
            <h3>Lista de equipas</h3>
            <div id="teamsTableContainer"></div>
          </div>
        </div>

        <!-- JOGADORES -->
        <?php require_once __DIR__ . '/api/csrf.php';
        require_once __DIR__ . '/api/connection.php'; ?>
        <div id="players-tab-content">
          <form id="formPlayer" action="/futsal-pj/api/create_player.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <label>Nome <input name="name" required></label>
            <label>Número <input name="number" type="text"></label>
            <!-- posição: select com opção Outra -->
            <label>Posição
              <select id="position_select" name="position_select">
                <option value="">-- Selecionar posição --</option>
                <option value="Guarda-Redes">Guarda-Redes</option>
                <option value="Fixo">Fixo</option>
                <option value="Ala">Ala</option>
                <option value="Pivot">Pivot</option>
                <option value="Universal">Universal</option>
                <option value="Outra">Outra...</option>
              </select>
            </label>
            <label id="position_other_wrap" style="display:none">Especifique
              <input id="position_other" type="text" placeholder="Ex.: Ala-pivô">
            </label>

            <!-- hidden final que o servidor espera -->
            <input type="hidden" name="position" id="position_hidden" value="">

            <label>Data Nasc <input name="dob" type="date"></label>
            <label>BI <input name="bi" type="text"></label>
            <label>Equipa
              <select name="team_id">
                <option value="0">-- Sem equipa --</option>
                <?php
                $m = get_mysqli();
                $r = $m->query("SELECT id, name FROM teams ORDER BY name");
                while ($row = $r->fetch_assoc()) {
                  echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['name']) . '</option>';
                }
                ?>
              </select>
            </label>
            <label>Foto (opcional) <input type="file" name="photo" accept="image/png, image/jpeg, image/webp"></label>
            <button type="submit">Criar Jogador</button>
          </form>

          <div id="playerResult" style="margin-top:10px"></div>
        </div>

        <script src="assets/js/ad_player.js">
        </script>



        <!-- STAFF -->
        <div id="panel-staff" class="panel" data-role="panel" style="display:none">
          <h2>Staff</h2>
          <p class="small">Treinadores e equipa técnica.</p>
          <div id="staffList"></div>
        </div>

        <!-- FASES & GRUPOS -->
        <div id="panel-phases" class="panel" data-role="panel" style="display:none">
          <h2>Fases & Grupos</h2>
          <p class="small">Criar fases (group/knockout) e associar equipas a grupos.</p>
          <div id="phasesUI"></div>
        </div>

        <!-- FIXTURES -->
        <div id="panel-fixtures" class="panel" data-role="panel" style="display:none">
          <h2>Agenda / Jogos</h2>
          <p class="small">Agende partidas, edite resultados e visualize calendário.</p>
          <div id="fixtureControls"></div>
          <div id="fixturesList" style="margin-top:12px"></div>
        </div>

        <!-- RELATÓRIOS -->
        <div id="panel-reports" class="panel" data-role="panel" style="display:none">
          <h2>Relatórios</h2>
          <p class="small">Exportar classificações, estatísticas e eventos.</p>
          <div id="reportsArea"></div>
        </div>

        <!-- CONFIGURAÇÕES -->
        <div id="panel-settings" class="panel" data-role="panel" style="display:none">
          <h2>Configurações</h2>
          <p class="small">Preferências do painel e dados do torneio.</p>
          <div id="settingsArea"></div>
        </div>

      </section>
    </main>
  </div>

  <!-- Toast global -->
  <div id="globalToast" role="status" aria-live="polite" style="position:fixed;right:18px;bottom:18px;display:none">
  </div>

  <script src="ad_panel_tabs.js"></script>
</body>

</html>