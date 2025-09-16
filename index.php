<?php
// index.php
require_once __DIR__ . '/new-api/connection.php';

$m = get_mysqli();
$m->set_charset('utf8mb4');
function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Torneio atual/mais recente
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
if (!$tournament_id) {
  $t = $m->query("SELECT id,name,start_date,end_date FROM tournaments ORDER BY start_date DESC, id DESC LIMIT 1")->fetch_assoc();
} else {
  $st = $m->prepare("SELECT id,name,start_date,end_date FROM tournaments WHERE id=?");
  $st->bind_param('i', $tournament_id);
  $st->execute();
  $t = $st->get_result()->fetch_assoc();
  $st->close();
}
if (!$t) {
  http_response_code(404);
  echo "<p>Sem torneios.</p>";
  exit;
}
$tournament_id = (int)$t['id'];

// Utilitário: detectar colunas
function has_col(mysqli $m, string $table, string $col): bool
{
  $dbRow = $m->query("SELECT DATABASE()")->fetch_row();
  $db = $dbRow ? $dbRow[0] : '';
  $st = $m->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
  $st->bind_param('sss', $db, $table, $col);
  $st->execute();
  $cnt = (int)$st->get_result()->fetch_row()[0];
  $st->close();
  return $cnt > 0;
}
$hasStatus      = has_col($m, 'matches', 'status');
$hasGroupLabel  = has_col($m, 'teams',   'group_label');

// Condições de “finalizado” / “agendado” com fallback por data
$COND_FINISHED = $hasStatus ? "m.status = 'finalizado'" : "(m.match_date IS NOT NULL AND m.match_date <= NOW())";
$COND_SCHEDULE = $hasStatus ? "m.status = 'agendado'"   : "(m.match_date IS NOT NULL AND m.match_date >= NOW())";

// Regras de pontuação
$points_win = 3;
$points_draw = 1;
$points_loss = 0;

// Função comum para standings do torneio inteiro
function get_standings_all(mysqli $m, int $tournament_id, int $pw, int $pd, int $pl, string $COND_FINISHED): array
{
  $sql = "
SELECT
  tt.id AS team_id,
  tt.name AS team_name,
  COALESCE(tt.logo_url,'') AS logo_url,
  COALESCE(SUM(x.played),0) AS played,
  COALESCE(SUM(x.won),0) AS won,
  COALESCE(SUM(x.drawn),0) AS drawn,
  COALESCE(SUM(x.lost),0) AS lost,
  COALESCE(SUM(x.gf),0) AS gf,
  COALESCE(SUM(x.ga),0) AS ga,
  COALESCE(SUM(x.points),0) AS points
FROM teams tt
LEFT JOIN (
  SELECT m.team_home_id AS team_id, 1 AS played,
    (m.home_score > m.away_score) AS won,
    (m.home_score = m.away_score) AS drawn,
    (m.home_score < m.away_score) AS lost,
    m.home_score AS gf, m.away_score AS ga,
    CASE WHEN m.home_score > m.away_score THEN {$pw}
         WHEN m.home_score = m.away_score THEN {$pd}
         ELSE {$pl} END AS points
  FROM matches m JOIN phases p ON p.id = m.phase_id
  WHERE p.tournament_id = ? AND {$COND_FINISHED}

  UNION ALL

  SELECT m.team_away_id AS team_id, 1 AS played,
    (m.away_score > m.home_score) AS won,
    (m.away_score = m.home_score) AS drawn,
    (m.away_score < m.home_score) AS lost,
    m.away_score AS gf, m.home_score AS ga,
    CASE WHEN m.away_score > m.home_score THEN {$pw}
         WHEN m.away_score = m.home_score THEN {$pd}
         ELSE {$pl} END AS points
  FROM matches m JOIN phases p ON p.id = m.phase_id
  WHERE p.tournament_id = ? AND {$COND_FINISHED}
) x ON x.team_id = tt.id
WHERE tt.tournament_id = ?
GROUP BY tt.id, tt.name, tt.logo_url
ORDER BY points DESC, (SUM(COALESCE(x.gf,0))-SUM(COALESCE(x.ga,0))) DESC, SUM(COALESCE(x.gf,0)) DESC, tt.name ASC";
  $st = $m->prepare($sql);
  $st->bind_param('iii', $tournament_id, $tournament_id, $tournament_id);
  $st->execute();
  $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
  $st->close();
  return $rows;
}

// Standings por grupo (preferindo teams.group_label; se não existir, fallback para phase_teams + phases nomeados “Grupo A/B”)
function get_standings_group(mysqli $m, int $tournament_id, string $group, int $pw, int $pd, int $pl, string $COND_FINISHED, bool $hasGroupLabel): array
{
  if ($hasGroupLabel) {
    $sql = "
SELECT
  tt.id AS team_id,
  tt.name AS team_name,
  COALESCE(tt.logo_url,'') AS logo_url,
  COALESCE(SUM(x.played),0) AS played,
  COALESCE(SUM(x.won),0) AS won,
  COALESCE(SUM(x.drawn),0) AS drawn,
  COALESCE(SUM(x.lost),0) AS lost,
  COALESCE(SUM(x.gf),0) AS gf,
  COALESCE(SUM(x.ga),0) AS ga,
  COALESCE(SUM(x.points),0) AS points
FROM teams tt
LEFT JOIN (
  SELECT m.team_home_id AS team_id, 1 AS played,
    (m.home_score > m.away_score) AS won,
    (m.home_score = m.away_score) AS drawn,
    (m.home_score < m.away_score) AS lost,
    m.home_score AS gf, m.away_score AS ga,
    CASE WHEN m.home_score > m.away_score THEN {$pw}
         WHEN m.home_score = m.away_score THEN {$pd}
         ELSE {$pl} END AS points
  FROM matches m JOIN phases p ON p.id = m.phase_id
  WHERE p.tournament_id = ? AND {$COND_FINISHED}

  UNION ALL

  SELECT m.team_away_id AS team_id, 1 AS played,
    (m.away_score > m.home_score) AS won,
    (m.away_score = m.home_score) AS drawn,
    (m.away_score < m.home_score) AS lost,
    m.away_score AS gf, m.home_score AS ga,
    CASE WHEN m.away_score > m.home_score THEN {$pw}
         WHEN m.away_score = m.home_score THEN {$pd}
         ELSE {$pl} END AS points
  FROM matches m JOIN phases p ON p.id = m.phase_id
  WHERE p.tournament_id = ? AND {$COND_FINISHED}
) x ON x.team_id = tt.id
WHERE tt.tournament_id = ? AND tt.group_label = ?
GROUP BY tt.id, tt.name, tt.logo_url
ORDER BY points DESC, (SUM(COALESCE(x.gf,0))-SUM(COALESCE(x.ga,0))) DESC, SUM(COALESCE(x.gf,0)) DESC, tt.name ASC";
    $st = $m->prepare($sql);
    $st->bind_param('iiis', $tournament_id, $tournament_id, $tournament_id, $group);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
  } else {
    // Fallback: usa phase_teams + phases “Grupo X”
    $sql = "
SELECT
  tt.id AS team_id,
  tt.name AS team_name,
  COALESCE(tt.logo_url,'') AS logo_url,
  COALESCE(SUM(x.played),0) AS played,
  COALESCE(SUM(x.won),0) AS won,
  COALESCE(SUM(x.drawn),0) AS drawn,
  COALESCE(SUM(x.lost),0) AS lost,
  COALESCE(SUM(x.gf),0) AS gf,
  COALESCE(SUM(x.ga),0) AS ga,
  COALESCE(SUM(x.points),0) AS points
FROM teams tt
LEFT JOIN (
  SELECT m.team_home_id AS team_id, 1 AS played,
    (m.home_score > m.away_score) AS won,
    (m.home_score = m.away_score) AS drawn,
    (m.home_score < m.away_score) AS lost,
    m.home_score AS gf, m.away_score AS ga,
    CASE WHEN m.home_score > m.away_score THEN {$pw}
         WHEN m.home_score = m.away_score THEN {$pd}
         ELSE {$pl} END AS points
  FROM matches m JOIN phases p ON p.id = m.phase_id
  WHERE p.tournament_id = ? AND {$COND_FINISHED}

  UNION ALL

  SELECT m.team_away_id AS team_id, 1 AS played,
    (m.away_score > m.home_score) AS won,
    (m.away_score = m.home_score) AS drawn,
    (m.away_score < m.home_score) AS lost,
    m.away_score AS gf, m.home_score AS ga,
    CASE WHEN m.away_score > m.home_score THEN {$pw}
         WHEN m.away_score = m.home_score THEN {$pd}
         ELSE {$pl} END AS points
  FROM matches m JOIN phases p ON p.id = m.phase_id
  WHERE p.tournament_id = ? AND {$COND_FINISHED}
) x ON x.team_id = tt.id
WHERE tt.tournament_id = ?
  AND tt.id IN (
    SELECT pt.team_id
    FROM phase_teams pt
    JOIN phases p2 ON p2.id = pt.phase_id
    WHERE p2.tournament_id = ?
      AND p2.name = CONCAT('Grupo ', ?)
  )
GROUP BY tt.id, tt.name, tt.logo_url
ORDER BY points DESC, (SUM(COALESCE(x.gf,0))-SUM(COALESCE(x.ga,0))) DESC, SUM(COALESCE(x.gf,0)) DESC, tt.name ASC";
    $st = $m->prepare($sql);
    $st->bind_param('iiiis', $tournament_id, $tournament_id, $tournament_id, $tournament_id, $group);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
  }
}

$standAll = get_standings_all($m, $tournament_id, $points_win, $points_draw, $points_loss, $COND_FINISHED);
$standA   = get_standings_group($m, $tournament_id, 'A', $points_win, $points_draw, $points_loss, $COND_FINISHED, $hasGroupLabel);
$standB   = get_standings_group($m, $tournament_id, 'B', $points_win, $points_draw, $points_loss, $COND_FINISHED, $hasGroupLabel);

// Preparar “Forma” (últimos 5) e “Próximo”
$teamIds = array_values(array_unique(array_merge(
  array_map(fn($r) => (int)$r['team_id'], $standAll),
  array_map(fn($r) => (int)$r['team_id'], $standA),
  array_map(fn($r) => (int)$r['team_id'], $standB),
)));
$teamIdsIn = $teamIds ? implode(',', $teamIds) : '0';

// Resultados finalizados para “Forma”
$finalRows = $m->query("
  SELECT m.id, m.match_date, m.team_home_id, m.team_away_id, m.home_score, m.away_score
  FROM matches m
  JOIN phases p ON p.id = m.phase_id
  WHERE p.tournament_id = {$tournament_id}
    AND {$COND_FINISHED}
  ORDER BY m.match_date DESC, m.id DESC
")->fetch_all(MYSQLI_ASSOC);

// Próximos (agendados)
$nextRows = $m->query("
  SELECT m.id, m.match_date, m.team_home_id, m.team_away_id
  FROM matches m
  JOIN phases p ON p.id = m.phase_id
  WHERE p.tournament_id = {$tournament_id}
    AND {$COND_SCHEDULE}
  ORDER BY m.match_date ASC
")->fetch_all(MYSQLI_ASSOC);

$forms = [];
$nextFix = [];
foreach ($teamIds as $tid) $forms[$tid] = [];

foreach ($finalRows as $mr) {
  $hid = (int)$mr['team_home_id'];
  $aid = (int)$mr['team_away_id'];
  $hs = (int)$mr['home_score'];
  $as = (int)$mr['away_score'];
  $rid = (int)$mr['id'];
  $date = substr($mr['match_date'] ?? '', 0, 16);
  if (isset($forms[$hid]) && count($forms[$hid]) < 5) $forms[$hid][] = ['id' => $rid, 'date' => $date, 'result' => ($hs > $as ? 'V' : ($hs === $as ? 'E' : 'P')), 'opp_id' => $aid, 'score' => "$hs-$as"];
  if (isset($forms[$aid]) && count($forms[$aid]) < 5) $forms[$aid][] = ['id' => $rid, 'date' => $date, 'result' => ($as > $hs ? 'V' : ($as === $hs ? 'E' : 'P')), 'opp_id' => $hid, 'score' => "$hs-$as"];
}

foreach ($nextRows as $nr) {
  $hid = (int)$nr['team_home_id'];
  $aid = (int)$nr['team_away_id'];
  $date = substr($nr['match_date'] ?? '', 0, 16);
  $rid = (int)$nr['id'];
  if (!isset($nextFix[$hid])) $nextFix[$hid] = ['id' => $rid, 'date' => $date, 'opp_id' => $aid];
  if (!isset($nextFix[$aid])) $nextFix[$aid] = ['id' => $rid, 'date' => $date, 'opp_id' => $hid];
}

// Info equipas (logo/nome)
$teamInfo = [];
if ($teamIds) {
  $res = $m->query("SELECT id,name,COALESCE(logo_url,'') AS logo_url FROM teams WHERE id IN ($teamIdsIn)");
  foreach ($res as $r) $teamInfo[(int)$r['id']] = $r;
}

// Card “Últimos Jogos Agendados” (próximos 5)
$lastGames = $m->query("
  SELECT
    m.id, m.match_date,
    th.name AS home_name, ta.name AS away_name,
    COALESCE(th.logo_url,'') AS home_logo,
    COALESCE(ta.logo_url,'') AS away_logo,
    p.name AS phase_name
  FROM matches m
  JOIN phases p ON p.id = m.phase_id
  JOIN teams th ON th.id = m.team_home_id
  JOIN teams ta ON ta.id = m.team_away_id
  WHERE p.tournament_id = {$tournament_id}
    AND {$COND_SCHEDULE}
  ORDER BY m.match_date ASC, m.id ASC
  LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Render row de uma tabela
function render_table(array $rows, array $forms, array $nextFix, array $teamInfo)
{
  $pos = 1;
  foreach ($rows as $r):
    $tid = (int)$r['team_id'];
    $gd = (int)$r['gf'] - (int)$r['ga'];
    $logo = $r['logo_url'] ?: 'assets/img/placeholder-team.png';
    $fives = $forms[$tid] ?? [];
    $next = $nextFix[$tid] ?? null;
    $oppName = $next && isset($teamInfo[$next['opp_id']]) ? $teamInfo[$next['opp_id']]['name'] : '';
    $lost = (int)$r['lost'];
?>
    <tr>
      <td data-cell="Pos"><?= $pos++ ?></td>
      <td data-cell="Clube">
        <div class="club"><img src="<?= h($logo) ?>" alt=""><span><?= h($r['team_name']) ?></span></div>
      </td>
      <td class="hide-sm" data-cell="Partidas"><?= (int)$r['played'] ?></td>
      <td data-cell="Vitorias"><?= (int)$r['won'] ?></td>
      <td data-cell="Empates"><?= (int)$r['drawn'] ?></td>
      <td data-cell="Perdas"><?= $lost ?></td>
      <td data-cell="GF"><?= (int)$r['gf'] ?></td>
      <td data-cell="GA"><?= (int)$r['ga'] ?></td>
      <td data-cell="GD"><?= $gd ?></td>
      <td data-cell="Pontos"><strong><?= (int)$r['points'] ?></strong></td>
      <td data-cell="Forma">
        <div class="form">
          <?php foreach ($fives as $f): ?>
            <a class="pill <?= h($f['result']) ?>" href="match.php?id=<?= (int)$f['id'] ?>"
              title="<?= h($f['date'] . ' • ' . $f['score']) ?>"><?= h($f['result']) ?></a>
          <?php endforeach; ?>
        </div>
      </td>
      <td class="next hide-sm" data-cell="Próximo">
        <?php if ($next): ?>
          <a href="match.php?id=<?= (int)$next['id'] ?>">
            <?= h($oppName) ?> — <?= h($next['date']) ?>
          </a>
          <?php else: ?>—<?php endif; ?>
      </td>
    </tr>
<?php
  endforeach;
}
?>
<!doctype html>
<html lang="pt">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/css/standings.css">
  <title>Classificação — <?= h($t['name']) ?></title>
</head>

<body>
  <header>
    <section class="top-section-header">
      <div class="last-games" style="max-width:none; height:auto;">
        <div class="texts" style="justify-content:flex-start; gap:1rem; padding:.6rem 1rem;">
          <h2 class="txtlast">Últimos Jogos Agendados</h2>
        </div>
        <div class="last-games-row" style="padding: 0 1rem 1rem;">
          <?php if (empty($lastGames)): ?>
            <div class="game-card">Sem jogos agendados.</div>
            <?php else: foreach ($lastGames as $g):
              $date = $g['match_date'] ? date('Y-m-d H:i', strtotime($g['match_date'])) : '—';
              $homeLogo = $g['home_logo'] ?: "assets/img/placeholder-team.png";
              $awayLogo = $g['away_logo'] ?: "assets/img/placeholder-team.png";
            ?>
              <a class="game-card" href="match.php?id=<?= (int)$g['id'] ?>" title="<?= h($g['phase_name'] ?? '') ?>">
                <div class="gc-header">
                  <span class="gc-date"><?= h($date) ?></span>
                  <span class="gc-phase"><?= h($g['phase_name'] ?? '') ?></span>
                </div>
                <div class="gc-teams">
                  <div class="gc-side">
                    <img class="gc-logo" src="<?= h($homeLogo) ?>" alt="">
                    <span class="gc-name"><?= h($g['home_name']) ?></span>
                  </div>
                  <div class="gc-score">vs</div>
                  <div class="gc-side gc-right">
                    <span class="gc-name"><?= h($g['away_name']) ?></span>
                    <img class="gc-logo" src="<?= h($awayLogo) ?>" alt="">
                  </div>
                </div>
              </a>
          <?php endforeach;
          endif; ?>
        </div>
      </div>
    </section>

    <section class="middle-section-header">
      <nav>
        <div class="logo"><a href="index.php"><img src="assets/img/logo.jpeg" alt=""></a></div>
        <div class="links" id="primary-nav">
          <a class="nav-link active" href="index.php">Classificação</a>
          <a class="nav-link" href="last_matches.php">Jogos Passados</a>
        </div>
      </nav>
    </section>

    <section class="bottom-section-header overlay"></section>
  </header>

  <main>
    <!-- Tabela Geral -->
    <div class="wrap standings-wrap">
      <div class="topbar">
        <h2 style="margin:0">Classificação Geral — <?= h($t['name']) ?></h2>
      </div>
      <div class="table-conteiner">
        <table class="table">
          <thead>
            <tr>
              <th>Pos</th>
              <th>Clube</th>
              <th class="hide-sm">Partidas</th>
              <th>Vitorias</th>
              <th>Empates</th>
              <th>Perdas</th>
              <th>GF</th>
              <th>GA</th>
              <th>GD</th>
              <th>Pontos</th>
              <th>Jogos</th>
              <th class="hide-sm">Próximo</th>
            </tr>
          </thead>
          <tbody><?php render_table($standAll, $forms, $nextFix, $teamInfo); ?></tbody>
        </table>
      </div>
    </div>

    <!-- Grupo A -->
    <div class="wrap standings-wrap">
      <div class="topbar">
        <h2 style="margin:0">Classificação — Grupo A</h2>
      </div>
      <div class="table-conteiner">
        <table class="table">
          <thead>
            <tr>
              <th>Pos</th>
              <th>Clube</th>
              <th class="hide-sm">Partidas</th>
              <th>Vitorias</th>
              <th>Empates</th>
              <th>Perdas</th>
              <th>GF</th>
              <th>GA</th>
              <th>GD</th>
              <th>Pontos</th>
              <th>Jogos</th>
              <th class="hide-sm">Próximo</th>
            </tr>
          </thead>
          <tbody><?php render_table($standA, $forms, $nextFix, $teamInfo); ?></tbody>
        </table>
      </div>
    </div>

    <!-- Grupo B -->
    <div class="wrap standings-wrap">
      <div class="topbar">
        <h2 style="margin:0">Classificação — Grupo B</h2>
      </div>
      <div class="table-conteiner">
        <table class="table">
          <thead>
            <tr>
              <th>Pos</th>
              <th>Clube</th>
              <th class="hide-sm">Partidas</th>
              <th>Vitorias</th>
              <th>Empates</th>
              <th>Perdas</th>
              <th>GF</th>
              <th>GA</th>
              <th>GD</th>
              <th>Pontos</th>
              <th>Jogos</th>
              <th class="hide-sm">Próximo</th>
            </tr>
          </thead>
          <tbody><?php render_table($standB, $forms, $nextFix, $teamInfo); ?></tbody>
        </table>
      </div>
    </div>
  </main>

  <?php include __DIR__ . '/includes/footer.php'; ?>
</body>

</html>