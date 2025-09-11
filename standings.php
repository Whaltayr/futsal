<?php
// public/standings.php
require_once __DIR__ . '/../new-api/connection.php';

$m = get_mysqli();
$m->set_charset('utf8mb4');

// 1) Pick tournament (from ?tournament_id= or latest by start_date)
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;

if (!$tournament_id) {
  $q = $m->query("SELECT id, name, start_date, end_date FROM tournaments ORDER BY start_date DESC, id DESC LIMIT 1");
  $t = $q ? $q->fetch_assoc() : null;
} else {
  $stmt = $m->prepare("SELECT id, name, start_date, end_date FROM tournaments WHERE id=?");
  $stmt->bind_param('i', $tournament_id);
  $stmt->execute();
  $t = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}
if (!$t) {
  http_response_code(404);
  echo "<p>Sem torneios.</p>";
  exit;
}
$tournament_id = (int)$t['id'];

// 2) Aggregate standings (count only finalizado)
$sql = "
SELECT
  tt.id AS team_id,
  tt.name AS team_name,
  COALESCE(tt.logo, '') AS logo,
  SUM(x.played) AS played,
  SUM(x.won) AS won,
  SUM(x.drawn) AS drawn,
  SUM(x.lost) AS lost,
  SUM(x.gf) AS gf,
  SUM(x.ga) AS ga,
  SUM(x.points) AS points
FROM (
  SELECT
    m.team_home_id AS team_id,
    1 AS played,
    CASE WHEN m.home_score > m.away_score THEN 1 ELSE 0 END AS won,
    CASE WHEN m.home_score = m.away_score THEN 1 ELSE 0 END AS drawn,
    CASE WHEN m.home_score < m.away_score THEN 1 ELSE 0 END AS lost,
    m.home_score AS gf,
    m.away_score AS ga,
    CASE
      WHEN m.home_score > m.away_score THEN 3
      WHEN m.home_score = m.away_score THEN 1
      ELSE 0
    END AS points
  FROM matches m
  WHERE m.status = 'finalizado'
    AND m.tournament_id = ?
    AND m.home_score IS NOT NULL
    AND m.away_score IS NOT NULL

  UNION ALL

  SELECT
    m.team_away_id AS team_id,
    1 AS played,
    CASE WHEN m.away_score > m.home_score THEN 1 ELSE 0 END AS won,
    CASE WHEN m.away_score = m.home_score THEN 1 ELSE 0 END AS drawn,
    CASE WHEN m.away_score < m.home_score THEN 1 ELSE 0 END AS lost,
    m.away_score AS gf,
    m.home_score AS ga,
    CASE
      WHEN m.away_score > m.home_score THEN 3
      WHEN m.away_score = m.home_score THEN 1
      ELSE 0
    END AS points
  FROM matches m
  WHERE m.status = 'finalizado'
    AND m.tournament_id = ?
    AND m.home_score IS NOT NULL
    AND m.away_score IS NOT NULL
) x
JOIN teams tt ON tt.id = x.team_id
WHERE tt.tournament_id = ?
GROUP BY tt.id, tt.name, tt.logo
ORDER BY points DESC, (SUM(x.gf) - SUM(x.ga)) DESC, SUM(x.gf) DESC, tt.name ASC
";
$stmt = $m->prepare($sql);
$stmt->bind_param('iii', $tournament_id, $tournament_id, $tournament_id);
$stmt->execute();
$stand = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 3) Build a map for last 5 results (“Form”) and next fixture per team
$teamIds = array_map(fn($r) => (int)$r['team_id'], $stand);
$teamIdsIn = $teamIds ? implode(',', $teamIds) : '0';

// fetch all final matches for form
$allFinal = $m->query("
  SELECT id, match_date, team_home_id, team_away_id, home_score, away_score
  FROM matches
  WHERE status = 'finalizado' AND tournament_id = {$tournament_id}
  ORDER BY match_date DESC, id DESC
")->fetch_all(MYSQLI_ASSOC);

// fetch next fixtures per team (soonest agendado)
$nextRows = $m->query("
  SELECT m.id, m.match_date, m.team_home_id, m.team_away_id
  FROM matches m
  WHERE m.status = 'agendado'
    AND m.tournament_id = {$tournament_id}
    AND m.match_date IS NOT NULL
    AND m.match_date >= NOW()
  ORDER BY m.match_date ASC
")->fetch_all(MYSQLI_ASSOC);

// index helpers
$forms = [];      // team_id => [ {id,date,result,opp_id,score} ... up to 5 ]
$nextFix = [];    // team_id => { id,date,opp_id }

foreach ($teamIds as $tid) { $forms[$tid] = []; }

// compute forms by scanning allFinal once
foreach ($allFinal as $mrow) {
  $hid = (int)$mrow['team_home_id'];
  $aid = (int)$mrow['team_away_id'];
  $hs  = (int)$mrow['home_score'];
  $as  = (int)$mrow['away_score'];
  $rid = (int)$mrow['id'];
  $date = substr($mrow['match_date'] ?? '', 0, 16);

  // home team perspective
  if (isset($forms[$hid]) && count($forms[$hid]) < 5) {
    $res = ($hs > $as) ? 'W' : (($hs === $as) ? 'D' : 'L');
    $forms[$hid][] = ['id'=>$rid,'date'=>$date,'result'=>$res,'opp_id'=>$aid,'score'=>"$hs-$as"];
  }
  // away team perspective
  if (isset($forms[$aid]) && count($forms[$aid]) < 5) {
    $res = ($as > $hs) ? 'W' : (($as === $hs) ? 'D' : 'L');
    $forms[$aid][] = ['id'=>$rid,'date'=>$date,'result'=>$res,'opp_id'=>$hid,'score'=>"$hs-$as"];
  }
}

// compute next fixture (first upcoming match where team participates)
foreach ($nextRows as $nr) {
  $hid = (int)$nr['team_home_id'];
  $aid = (int)$nr['team_away_id'];
  $date = substr($nr['match_date'] ?? '', 0, 16);
  $rid = (int)$nr['id'];

  if (!isset($nextFix[$hid])) $nextFix[$hid] = ['id'=>$rid, 'date'=>$date, 'opp_id'=>$aid];
  if (!isset($nextFix[$aid])) $nextFix[$aid] = ['id'=>$rid, 'date'=>$date, 'opp_id'=>$hid];
}

// fetch team names/logos for opponents
$teamInfo = [];
if ($teamIds) {
  $resTI = $m->query("SELECT id, name, COALESCE(logo,'') AS logo FROM teams WHERE id IN ($teamIdsIn)");
  foreach ($resTI as $r) { $teamInfo[(int)$r['id']] = $r; }
}

// 4) Render HTML
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Classificação — <?= h($t['name']) ?></title>
  <link rel="stylesheet" href="/futsal-pj/assets/css/ad_painel.css">
  <style>
    /* Light public overrides for form pills + table fit */
    .standings { width: min(100% - 2rem, 1200px); margin: 1rem auto; }
    .standings h2 { color: #111; margin: 0 0 .5rem; }
    .standings .table { color: #111; }
    .club-cell { display:flex; align-items:center; gap:8px; }
    .club-cell img { width: 26px; height: 26px; border-radius: 50%; object-fit: cover; background:#eee; }
    .form { display:flex; gap:6px; }
    .form .pill { width:22px; height:22px; border-radius:50%; display:grid; place-items:center; font-size:.8rem; font-weight:800; color:#fff; text-decoration:none; }
    .pill.W { background:#16a34a; } /* green */
    .pill.D { background:#6b7280; } /* gray */
    .pill.L { background:#ef4444; } /* red */
    .next a { color:#0f172a; text-decoration: underline; }
    @media (max-width: 720px) {
      .hide-sm { display:none; }
    }
  </style>
</head>
<body>
  <div class="standings">
    <h2>Classificação — <?= h($t['name']) ?></h2>
    <div class="table-conteiner">
      <table class="table">
        <thead>
          <tr>
            <th>Pos</th>
            <th>Clube</th>
            <th class="hide-sm">J</th>
            <th>V</th>
            <th>E</th>
            <th>D</th>
            <th>GF</th>
            <th>GA</th>
            <th>GD</th>
            <th>Pontos</th>
            <th>Form</th>
            <th class="hide-sm">Próximo</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $pos = 1;
          foreach ($stand as $row):
            $gd = (int)$row['gf'] - (int)$row['ga'];
            $tid = (int)$row['team_id'];
            $logo = $row['logo'] ?: '/futsal-pj/assets/img/placeholder-team.png';
            $fives = $forms[$tid] ?? [];
            $next = $nextFix[$tid] ?? null;
            $oppName = $next && isset($teamInfo[$next['opp_id']]) ? $teamInfo[$next['opp_id']]['name'] : '';
          ?>
          <tr>
            <td><?= $pos++ ?></td>
            <td>
              <div class="club-cell">
                <img src="<?= h($logo) ?>" alt="">
                <span><?= h($row['team_name']) ?></span>
              </div>
            </td>
            <td class="hide-sm"><?= (int)$row['played'] ?></td>
            <td><?= (int)$row['won'] ?></td>
            <td><?= (int)$row['drawn'] ?></td>
            <td><?= (int)$row['lost'] ?></td>
            <td><?= (int)$row['gf'] ?></td>
            <td><?= (int)$row['ga'] ?></td>
            <td><?= $gd ?></td>
            <td><strong><?= (int)$row['points'] ?></strong></td>
            <td>
              <div class="form">
                <?php foreach ($fives as $f): ?>
                  <a class="pill <?= h($f['result']) ?>" href="/futsal-pj/public/match.php?id=<?= (int)$f['id'] ?>" title="<?= h($f['date'].' • '.$f['score']) ?>"><?= h($f['result']) ?></a>
                <?php endforeach; ?>
              </div>
            </td>
            <td class="next hide-sm">
              <?php if ($next): ?>
                <a href="/futsal-pj/public/match.php?id=<?= (int)$next['id'] ?>">
                  <?= h($oppName) ?> — <?= h($next['date']) ?>
                </a>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>