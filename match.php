<?php
// TEMP: show errors to diagnose 500s. Remove in production.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/new-api/connection.php';
require_once __DIR__ . '/includes/utils.php'; // ensure helpers loaded once
if (!function_exists('get_mysqli')) {
  http_response_code(500);
  echo "Erro: função get_mysqli() não encontrada. Verifique connection.php.";
  exit;
}

$m = get_mysqli();
$m->set_charset('utf8mb4');



// 1) Validate ID
$match_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$match_id) {
  http_response_code(400);
  echo "ID inválido";
  exit;
}

// 2) Fetch match + related
$sql = "
SELECT
  m.id, m.match_date, m.home_score, m.away_score, m.status, m.round,
  th.id   AS home_id, th.name AS home_name, COALESCE(th.logo_url,'') AS home_logo,
  ta.id   AS away_id, ta.name AS away_name, COALESCE(ta.logo_url,'') AS away_logo,
  p.id    AS phase_id, p.name AS phase_name,
  t.id    AS tournament_id, t.name AS tournament_name
FROM matches m
JOIN phases p      ON p.id = m.phase_id
JOIN tournaments t ON t.id = p.tournament_id
JOIN teams th      ON th.id = m.team_home_id
JOIN teams ta      ON ta.id = m.team_away_id
WHERE m.id = ?
LIMIT 1";
$st = $m->prepare($sql);
if (!$st) {
  http_response_code(500);
  echo "SQL prepare falhou: " . h($m->error);
  exit;
}
$st->bind_param('i', $match_id);
if (!$st->execute()) {
  http_response_code(500);
  echo "SQL execute falhou: " . h($st->error);
  exit;
}
$res = $st->get_result();
$match = $res ? $res->fetch_assoc() : null;
$st->close();

if (!$match) {
  http_response_code(404);
  echo "Jogo não encontrado (id=" . (int)$match_id . ").";
  exit;
}

// Logos e data
$homeLogo = $match['home_logo'] ?: 'assets/img/placeholder-team.png';
$awayLogo = $match['away_logo'] ?: 'assets/img/placeholder-team.png';
$dateStr  = !empty($match['match_date']) ? date('Y-m-d H:i', strtotime($match['match_date'])) : '—';

// Status helpers
$isFinal  = ($match['status'] === 'finalizado' && $match['home_score'] !== null && $match['away_score'] !== null);

// 4) Recent H2H (within same tournament)
$h2h = [];
$hh = $m->prepare("
  SELECT m.id, m.match_date, m.home_score, m.away_score, m.team_home_id, m.team_away_id
  FROM matches m
  JOIN phases p ON p.id = m.phase_id
  WHERE p.tournament_id = ?
    AND m.status = 'finalizado'
    AND m.home_score IS NOT NULL AND m.away_score IS NOT NULL
    AND (
      (m.team_home_id = ? AND m.team_away_id = ?) OR
      (m.team_home_id = ? AND m.team_away_id = ?)
    )
  ORDER BY m.match_date DESC, m.id DESC
  LIMIT 5
");
if ($hh) {
  $tid = (int)$match['tournament_id'];
  $homeId = (int)$match['home_id'];
  $awayId = (int)$match['away_id'];
  $hh->bind_param('iiiii', $tid, $homeId, $awayId, $awayId, $homeId);
  if ($hh->execute()) {
    $h2h = $hh->get_result()->fetch_all(MYSQLI_ASSOC);
  }
  $hh->close();
}

// Datas amigáveis
$dt = !empty($match['match_date']) ? new DateTime($match['match_date']) : null;
$dateHuman = $dt ? $dt->format('j F Y') : '—';
$timeHuman = $dt ? $dt->format('H:i') : '—';

// Temporada (fallback simples)
$season = '—';
if ($dt) {
  $y = (int)$dt->format('Y');
  $season = $y . '/' . ($y + 1);
}

// Jornada
$matchDay = !empty($match['round']) ? (string)$match['round'] : '—';

// Tempo: mostrar 60' quando finalizado; “Em andamento” quando live; “—” quando agendado
$fullTime = ($match['status'] === 'finalizado') ? "60'"
  : (($match['status'] === 'em_andamento') ? "Em andamento" : "—");

// Label de status
$statusLabel = $match['status'] === 'finalizado' ? 'Finalizado'
  : ($match['status'] === 'em_andamento' ? 'Em andamento' : 'Agendado');
?>
<!doctype html>
<html lang="pt">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($match['home_name']) ?> x <?= h($match['away_name']) ?> — <?= h($match['tournament_name']) ?></title>
  <link rel="stylesheet" href="assets/css/standings.css">
  <style>
    :root {
      --bg: #ffffff;
      --border: #e5e7eb;
      --muted: #6b7280;
      --text: #0f172a;
      --pillV: #16a34a;
      --pillE: #fb923c;
      --pillP: #ef4444;
    }

    body {
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Arial;
      margin: 0;
      color: var(--text);
      background: var(--bg);
    }

    .wrap {
      width: min(100% - 2rem, 900px);
      margin: 1.2rem auto;
    }

    .card {
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 1rem;
      background: #fff;
      box-shadow: 0 2px 4px rgba(12, 23, 34, 0.36);
    }

    .header {
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      align-items: center;
      gap: 12px;
    }

    .team {
      display: flex;
      align-items: center;
      gap: 10px;
      justify-content: flex-start;
    }

    .team.right {
      justify-content: flex-end;
    }

    .team img {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      object-fit: cover;
      background: #eee;
    }

    .vs {
      text-align: center;
    }

    .score {
      font-size: 2.4rem;
      font-weight: 800;
    }

    .meta {
      margin-top: 8px;
      color: var(--muted);
      text-align: center;
    }

    .grid {
      display: grid;
      gap: 12px;
      grid-template-columns: 1fr;
      margin-top: 14px;
    }

    .section {
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 12px;
      background: #fff;
    }

    .section h4 {
      margin: .2rem 0 .6rem;
    }

    .list {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .list li {
      display: flex;
      justify-content: space-between;
      padding: .35rem 0;
      border-bottom: 1px dashed var(--border);
    }

    .list li:last-child {
      border-bottom: none;
    }

    .pill {
      display: inline-grid;
      place-items: center;
      width: 22px;
      height: 22px;
      border-radius: 50%;
      color: #fff;
      font-weight: 800;
      font-size: .8rem;
      text-decoration: none;
    }

    .pill.V {
      background: var(--pillV);
    }

    .pill.E {
      background: var(--pillE);
    }

    .pill.P {
      background: var(--pillP);
    }

    .h2h {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .muted {
      color: var(--muted);
    }

    a {
      color: inherit;
    }

    @media (max-width:640px) {
      .team span {
        display: none;
      }

      .score {
        font-size: 1.9rem;
      }
    }
    tbody > td{
      padding:.5rem .6rem; border-bottom:1px solid var(--border);
    }
  </style>
</head>

<body>

  <?php include __DIR__ . '/includes/header.php'; ?>

  <main class="wrap">
    <div class="card">
      <!-- Cabeçalho do jogo -->
      <div class="header">
        <div class="team left">
          <img src="<?= h($homeLogo) ?>" alt="">
          <span><?= h($match['home_name']) ?></span>
        </div>
        <div class="vs">
          <?php if ($isFinal): ?>
            <div class="score"><?= (int)$match['home_score'] ?> - <?= (int)$match['away_score'] ?></div>
            <div class="muted">Final • 60'</div>
          <?php elseif ($match['status'] === 'em_andamento'): ?>
            <div class="score">Em andamento</div>
          <?php else: ?>
            <div class="score"><?= h($match['round'] ?: 'Agendado') ?></div>
          <?php endif; ?>
        </div>
        <div class="team right">
          <span><?= h($match['away_name']) ?></span>
          <img src="<?= h($awayLogo) ?>" alt="">
        </div>
      </div>

      <div class="meta">
        <?= h($match['tournament_name']) ?> • <?= h($match['phase_name']) ?>
        <?php if (!empty($match['round'])): ?> • Jornada <?= h($match['round']) ?><?php endif; ?>
          <?php if (!empty($match['match_date'])): ?> • <?= h($dateStr) ?><?php endif; ?>
      </div>

      <!-- Detalhes -->
      <div class="grid">
        <div class="section">
          <h4>Detalhes</h4>
          <ul class="list">
            <li><span>Status</span><span class="muted"><?= h($statusLabel) ?></span></li>
            <li><span>Torneio</span><span class="muted"><?= h($match['tournament_name']) ?></span></li>
            <li><span>Fase</span><span class="muted"><?= h($match['phase_name']) ?></span></li>
            <?php if (!empty($match['round'])): ?>
              <li><span>Jornada</span><span class="muted"><?= h($match['round']) ?></span></li>
            <?php endif; ?>
            <li><span>Data</span><span class="muted"><?= h($dateStr) ?></span></li>
          </ul>

          <!-- Tabela de fatos -->
          <div class="facts" style="margin-top:12px">
            <table class="facts-table" style="width:100%; border-collapse:collapse;">
              <thead>
                <tr>
                  <th style="text-align:left; padding:.5rem .6rem; border-bottom:1px solid var(--border); color:var(--muted);">Data</th>
                  <th style="text-align:left; padding:.5rem .6rem; border-bottom:1px solid var(--border); color:var(--muted);">Hora</th>
                  <th style="text-align:left; padding:.5rem .6rem; border-bottom:1px solid var(--border); color:var(--muted);">Fase</th>
                  <th style="text-align:left; padding:.5rem .6rem; border-bottom:1px solid var(--border); color:var(--muted);">Temporada</th>
                  <th style="text-align:left; padding:.5rem .6rem; border-bottom:1px solid var(--border); color:var(--muted);">Status</th>
                  <th style="text-align:left; padding:.5rem .6rem; border-bottom:1px solid var(--border); color:var(--muted);">Tempo</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td style="padding:.5rem .6rem; border-bottom:1px solid var(--border);"><?= h($dateHuman) ?></td>
                  <td style="padding:.5rem .6rem; border-bottom:1px solid var(--border);"><?= h($timeHuman) ?></td>
                  <td style="padding:.5rem .6rem; border-bottom:1px solid var(--border);"><?= h($match['phase_name']) ?></td>
                  <td style=""><?= h($season) ?></td>
                  <td style="padding:.5rem .6rem; border-bottom:1px solid var(--border);"><?= h($statusLabel) ?></td>
                  <td style="padding:.5rem .6rem; border-bottom:1px solid var(--border);"><?= h($fullTime) ?></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <?php if ($h2h): ?>
          <div class="section">
            <h4>Confrontos Recentes</h4>
            <div class="h2h">
              <?php foreach ($h2h as $i => $r): ?>
                <?php
                $hs = (int)$r['home_score'];
                $as = (int)$r['away_score'];
                $isHome = ($r['team_home_id'] == (int)$match['home_id']);
                $letter = $hs == $as ? 'E' : (($hs > $as) ? ($isHome ? 'V' : 'P') : ($isHome ? 'P' : 'V'));
                ?>
                <div>
                  <span class="pill <?= $letter ?>"><?= $letter ?></span>
                  <span class="muted" style="margin-left:8px"><?= h(substr($r['match_date'], 0, 16)) ?></span>
                  <a style="margin-left:10px; text-decoration:underline" href="match.php?id=<?= (int)$r['id'] ?>">
                    <?= h($match['home_name']) ?> <?= $hs ?> - <?= $as ?> <?= h($match['away_name']) ?>
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>