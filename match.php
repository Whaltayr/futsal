<?php
// TEMP: show errors to diagnose 500s. Remove in production.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Adjust this include to your actual path.
// If connection.php is in the same folder as index.php and match.php:
require_once __DIR__ . '/new-api/connection.php';
// If yours is in new-api subfolder, use: require_once __DIR__ . '/new-api/connection.php';

if (!function_exists('get_mysqli')) {
  http_response_code(500);
  echo "Erro: função get_mysqli() não encontrada. Verifique connection.php.";
  exit;
}

$m = get_mysqli();
$m->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// 1) Validate ID
$match_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$match_id) { http_response_code(400); echo "ID inválido"; exit; }

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
if (!$st) { http_response_code(500); echo "SQL prepare falhou: " . h($m->error); exit; }
$st->bind_param('i', $match_id);
if (!$st->execute()) { http_response_code(500); echo "SQL execute falhou: " . h($st->error); exit; }
$res = $st->get_result();
$match = $res ? $res->fetch_assoc() : null;
$st->close();

if (!$match) { http_response_code(404); echo "Jogo não encontrado (id=". (int)$match_id .")."; exit; }

// 3) Render helpers
$homeLogo = $match['home_logo'] ?: '/futsal-pj/assets/img/placeholder-team.png';
$awayLogo = $match['away_logo'] ?: '/futsal-pj/assets/img/placeholder-team.png';
$dateStr  = !empty($match['match_date']) ? date('Y-m-d H:i', strtotime($match['match_date'])) : '—';
$isFinal  = ($match['status'] === 'finalizado' && $match['home_score'] !== null && $match['away_score'] !== null);

// 4) Recent H2H (within same tournament)
$h2h = [];
$hh = $m->prepare("
  SELECT
    m.id,
    m.match_date,
    m.home_score,
    m.away_score,
    m.team_home_id,
    m.team_away_id
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

// Build “match facts” values (paste after $dateStr and $isFinal)
$dt = !empty($match['match_date']) ? new DateTime($match['match_date']) : null;
$dateHuman = $dt ? $dt->format('j F Y') : '—';  // e.g., 14 June 2025
$timeHuman = $dt ? $dt->format('H:i') : '—';    // e.g., 14:00

// Season via tournament dates if available (optional), else from match year
// If your SELECT includes: t.start_date AS tournament_start, t.end_date AS tournament_end
$season = '—';
if (!empty($match['tournament_start']) && !empty($match['tournament_end'])) {
  try {
    $ys = (new DateTime($match['tournament_start']))->format('Y');
    $ye = (new DateTime($match['tournament_end']))->format('Y');
    $season = ($ys === $ye) ? $ys : ($ys . '/' . $ye);
  } catch (Throwable $e) {
    if ($dt) { $y = $dt->format('Y'); $season = $y . '/' . ((int)$y + 1); }
  }
} elseif ($dt) {
  $y = $dt->format('Y'); $season = $y . '/' . ((int)$y + 1);
}

// Jornada (Match Day)
$matchDay = !empty($match['round']) ? (string)$match['round'] : '—';

// Full Time cell (choose display)
$fullTime = ($match['status'] === 'finalizado') ? "FT"
          : (($match['status'] === 'em_andamento') ? "Live" : "—");
// If you prefer 60' for finalized futsal matches, use:
// $fullTime = ($match['status'] === 'finalizado') ? "60'" : (($match['status'] === 'em_andamento') ? "Live" : "—");
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($match['home_name']) ?> x <?= h($match['away_name']) ?> — <?= h($match['tournament_name']) ?></title>
  <style>
    :root {
      --bg:#ffffff; --border:#e5e7eb; --muted:#6b7280; --text:#0f172a;
      --pillV:#16a34a; --pillE:#fb923c; --pillP:#ef4444;
    }
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Arial; margin:0; color:var(--text); background:var(--bg); }
    .wrap { width:min(100% - 2rem, 900px); margin: 1.2rem auto; }
    .card { border:1px solid var(--border); border-radius:10px; padding:1rem; }
    .header { display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:12px; }
    .team { display:flex; align-items:center; gap:10px; justify-content:flex-start; }
    .team.right { justify-content:flex-end; }
    .team img { width:48px; height:48px; border-radius:50%; object-fit:cover; background:#eee; }
    .vs { text-align:center; }
    .score { font-size:2.2rem; font-weight:800; }
    .meta { margin-top:8px; color:var(--muted); text-align:center; }
    .grid { display:grid; gap:12px; grid-template-columns:1fr; margin-top:14px; }
    .section { border:1px solid var(--border); border-radius:10px; padding:12px; }
    .section h4 { margin:.2rem 0 .6rem; }
    .list { list-style:none; padding:0; margin:0; }
    .list li { display:flex; justify-content:space-between; padding:.35rem 0; border-bottom:1px dashed var(--border); }
    .list li:last-child { border-bottom:none; }
    .pill { display:inline-grid; place-items:center; width:22px; height:22px; border-radius:50%; color:#fff; font-weight:800; font-size:.8rem; text-decoration:none; }
    .pill.V { background:var(--pillV); }
    .pill.E { background:var(--pillE); }
    .pill.P { background:var(--pillP); }
    .h2h { display:flex; flex-direction:column; gap:8px; }
    .muted { color:var(--muted); }
    a { color:inherit; }
    @media (max-width:640px){ .team span { display:none; } .score { font-size:1.8rem; } }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <!-- Header -->
      <div class="header">
        <div class="team left">
          <img src="<?= h($homeLogo) ?>" alt="">
          <span><?= h($match['home_name']) ?></span>
        </div>
        <div class="vs">
          <?php if ($isFinal): ?>
            <div class="score"><?= (int)$match['home_score'] ?> - <?= (int)$match['away_score'] ?></div>
            <div class="muted">Final</div>
          <?php elseif ($match['status'] === 'em_andamento'): ?>
            <div class="score">Em andamento</div>
          <?php else: ?>
            <div class="score"><?= h($match['round']) ?></div>
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

      <!-- Details grid -->
      <div class="grid">
        <div class="section">
          <h4>Detalhes</h4>
          <ul class="list">
            <li><span>Status</span><span class="muted">
              <?php
                echo $match['status']==='finalizado' ? 'Finalizado' :
                     ($match['status']==='em_andamento' ? 'Em andamento' : 'Agendado');
              ?>
            </span></li>
            <li><span>Torneio</span><span class="muted"><?= h($match['tournament_name']) ?></span></li>
            <li><span>Fase</span><span class="muted"><?= h($match['phase_name']) ?></span></li>
            <?php if (!empty($match['round'])): ?>
            <li><span>Jornada</span><span class="muted"><?= h($match['round']) ?></span></li>
            <?php endif; ?>
            <li><span>Data</span><span class="muted"><?= h($dateStr) ?></span></li>

            <!-- Match facts row (paste right after </ul>, inside the same Details section) -->
  <div class="facts" style="margin-top:12px">
    <table class="facts-table" style="width:100%; border-collapse:collapse;">
      <thead>
        <tr>
          <th style="text-align:left; padding:.5rem .6rem; border-bottom:1px solid var(--border); color:var(--muted);">Date</th>
          <th style="text-align:left; padding:.5rem .6rem; border-bottom:1px solid var(--border); color:var(--muted);">Time</th>
          <th style="text-align:left; padding:.5rem .6rem; border-bottom:1px solid var(--border); color:var(--muted);">League</th>
          <th style="text-align:left; padding:.5rem .6rem; border-bottom:1px solid var(--border); color:var(--muted);">Season</th>
          <th style="text-align:left; padding:.5rem .6rem; border-bottom:1px solid var(--border); color:var(--muted);">Match Day</th>
          <th style="text-align:left; padding:.5rem .6rem; border-bottom:1px solid var(--border); color:var(--muted);">Full Time</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td style="padding:.5rem .6rem; border-bottom:1px solid var(--border);"><?= h($dateHuman) ?></td>
          <td style="padding:.5rem .6rem; border-bottom:1px solid var(--border);"><?= h($timeHuman) ?></td>
          <td style="padding:.5rem .6rem; border-bottom:1px solid var(--border);"><?= h($match['tournament_name']) ?></td>
          <td style="padding:.5rem .6rem; border-bottom:1px solid var(--border);"><?= h($season) ?></td>
          <td style="padding:.5rem .6rem; border-bottom:1px solid var(--border);"><?= h($matchDay) ?></td>
          <td style="padding:.5rem .6rem; border-bottom:1px solid var(--border);"><?= h($fullTime) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
          </ul>
        </div>

        <?php if ($h2h): ?>
        <div class="section">
          <h4>Confrontos Recentes</h4>
          <div class="h2h">
            <?php foreach ($h2h as $i => $r): ?>
              <?php
                $hs=(int)$r['home_score']; $as=(int)$r['away_score'];
                $isHome = ($r['team_home_id'] == (int)$match['home_id']);
                $letter = $hs==$as ? 'E' : (($hs>$as) ? ($isHome?'V':'P') : ($isHome?'P':'V'));
              ?>
              <div>
                <span class="pill <?= $letter ?>"><?= $letter ?></span>
                <span class="muted" style="margin-left:8px"><?= h(substr($r['match_date'],0,16)) ?></span>
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
  </div>
</body>
</html>