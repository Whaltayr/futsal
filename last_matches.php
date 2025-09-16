<?php
// last_matches.php
require_once __DIR__ . '/new-api/connection.php';
require_once __DIR__ . '/includes/utils.php'; // ensure helpers loaded once

$m = get_mysqli();
$m->set_charset('utf8mb4');
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Torneio atual/mais recente
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
if (!$tournament_id) {
  $t = $m->query("SELECT id,name FROM tournaments ORDER BY start_date DESC, id DESC LIMIT 1")->fetch_assoc();
} else {
  $st = $m->prepare("SELECT id,name FROM tournaments WHERE id=?");
  $st->bind_param('i', $tournament_id);
  $st->execute();
  $t = $st->get_result()->fetch_assoc();
  $st->close();
}
if (!$t) { http_response_code(404); echo "<p>Sem torneios.</p>"; exit; }
$tournament_id = (int)$t['id'];

// Detectar se existe coluna 'status' em matches (para fallback por data)
function has_col(mysqli $m, string $table, string $col): bool {
  $dbRow = $m->query("SELECT DATABASE()")->fetch_row();
  $db = $dbRow ? $dbRow[0] : '';
  $st = $m->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
  $st->bind_param('sss', $db, $table, $col);
  $st->execute();
  $cnt = (int)$st->get_result()->fetch_row()[0];
  $st->close();
  return $cnt > 0;
}
$hasStatus = has_col($m, 'matches', 'status');
$COND_FINISHED = $hasStatus
  ? "m.status = 'finalizado'"
  : "(m.match_date IS NOT NULL AND m.match_date <= NOW())";

// Buscar jogos finalizados (sem selects no navbar; aqui mostramos todos os jogos finalizados do torneio)
$sql = "
SELECT
  m.id, m.match_date,
  th.name AS home_name, ta.name AS away_name,
  COALESCE(th.logo_url,'') AS home_logo,
  COALESCE(ta.logo_url,'') AS away_logo,
  m.home_score, m.away_score,
  p.name AS phase_name
FROM matches m
JOIN phases p ON p.id = m.phase_id
JOIN teams th ON th.id = m.team_home_id
JOIN teams ta ON ta.id = m.team_away_id
WHERE p.tournament_id = ?
  AND {$COND_FINISHED}
  AND m.home_score IS NOT NULL AND m.away_score IS NOT NULL
ORDER BY m.match_date DESC, m.id DESC";
$st = $m->prepare($sql);
$st->bind_param('i', $tournament_id);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/css/standings.css">
  <title>Jogos Passados — <?= h($t['name']) ?></title>

  <style>
    .wrap{
      width: min(100%, 1000px);
      margin: 2rem auto 4rem;
      padding: 2rem 1rem;
    }
    .last-games-row > .game-card{
      box-shadow: 2px 5px 12px rgba(0, 0, 0, 0.23);
      border-radius: 5px;
    }
  </style>
</head>
<body>
  <header>
    <section class="top-section-header">
      <div class="last-games" style="max-width:none; height:auto;">
        <div class="texts" style="justify-content:flex-start; gap:1rem; padding:.6rem 1rem;">
          <h2 class="txtlast">Jogos Passados</h2>
        </div>
      </div>
    </section>

    <section class="middle-section-header">
      <nav>
        <div class="logo"><a href="index.php"><img src="assets/img/logo.jpeg" alt=""></a></div>
        <div class="links" id="primary-nav">
          <a class="nav-link" href="index.php">Classificação</a>
          <a class="nav-link active" href="last_matches.php">Jogos Passados</a>
        </div>
      </nav>
    </section>

    <section class="bottom-section-header overlay"></section>
  </header>

  <main class="wrap">
    <h2>Jogos Passados — <?= h($t['name']) ?></h2>
    <?php if (!$rows): ?>
      <p>Sem jogos finalizados.</p>
    <?php else: ?>
      <div class="last-games-row" style="gap:1rem;">
        <?php foreach ($rows as $g):
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
              <div class="gc-score"><?= (int)$g['home_score'] ?> - <?= (int)$g['away_score'] ?></div>
              <div class="gc-side gc-right">
                <span class="gc-name"><?= h($g['away_name']) ?></span>
                <img class="gc-logo" src="<?= h($awayLogo) ?>" alt="">
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>