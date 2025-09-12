<?php
// /futsal-pj/standings.php
require_once __DIR__ . '/new-api/connection.php';

$m = get_mysqli();
$m->set_charset('utf8mb4');
function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Pick tournament (?tournament_id=) or latest by start_date
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

// For the tournament dropdown
$tournaments = $m->query("SELECT id, name FROM tournaments ORDER BY start_date DESC, id DESC")->fetch_all(MYSQLI_ASSOC);

// SCORING RULE: V=3, E=2, P=0 (custom rule)
$points_win = 3;
$points_draw = 1;
$points_loss = 0;

// Aggregate standings via matches joined to phases
// Count only finalizado and non-NULL scores
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
  -- Home perspective
  SELECT
    m.team_home_id AS team_id,
    1 AS played,
    CASE WHEN m.home_score > m.away_score THEN 1 ELSE 0 END AS won,
    CASE WHEN m.home_score = m.away_score THEN 1 ELSE 0 END AS drawn,
    CASE WHEN m.home_score < m.away_score THEN 1 ELSE 0 END AS lost,
    m.home_score AS gf,
    m.away_score AS ga,
    CASE
      WHEN m.home_score > m.away_score THEN {$points_win}
      WHEN m.home_score = m.away_score THEN {$points_draw}
      ELSE {$points_loss}
    END AS points
  FROM matches m
  JOIN phases p ON p.id = m.phase_id
  WHERE p.tournament_id = ?
    AND m.status = 'finalizado'
    AND m.home_score IS NOT NULL
    AND m.away_score IS NOT NULL

  UNION ALL

  -- Away perspective
  SELECT
    m.team_away_id AS team_id,
    1 AS played,
    CASE WHEN m.away_score > m.home_score THEN 1 ELSE 0 END AS won,
    CASE WHEN m.away_score = m.home_score THEN 1 ELSE 0 END AS drawn,
    CASE WHEN m.away_score < m.home_score THEN 1 ELSE 0 END AS lost,
    m.away_score AS gf,
    m.home_score AS ga,
    CASE
      WHEN m.away_score > m.home_score THEN {$points_win}
      WHEN m.away_score = m.home_score THEN {$points_draw}
      ELSE {$points_loss}
    END AS points
  FROM matches m
  JOIN phases p ON p.id = m.phase_id
  WHERE p.tournament_id = ?
    AND m.status = 'finalizado'
    AND m.home_score IS NOT NULL
    AND m.away_score IS NOT NULL
) x ON x.team_id = tt.id
WHERE tt.tournament_id = ?
GROUP BY tt.id, tt.name, tt.logo_url
ORDER BY points DESC,
         (SUM(COALESCE(x.gf,0)) - SUM(COALESCE(x.ga,0))) DESC,
         SUM(COALESCE(x.gf,0)) DESC,
         tt.name ASC
";
$st = $m->prepare($sql);
$st->bind_param('iii', $tournament_id, $tournament_id, $tournament_id);
$st->execute();
$stand = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

// Build Form (last 5) and Next fixture for each team
$teamIds = array_map(fn($r) => (int)$r['team_id'], $stand);
$teamIdsIn = $teamIds ? implode(',', $teamIds) : '0';

// All final results (desc) to derive last 5 per team
$finalRows = $m->query("
  SELECT m.id, m.match_date, m.team_home_id, m.team_away_id, m.home_score, m.away_score
  FROM matches m
  JOIN phases p ON p.id = m.phase_id
  WHERE p.tournament_id = {$tournament_id}
    AND m.status = 'finalizado'
    AND m.home_score IS NOT NULL AND m.away_score IS NOT NULL
  ORDER BY m.match_date DESC, m.id DESC
")->fetch_all(MYSQLI_ASSOC);

// Upcoming matches (asc) to derive next per team
$nextRows = $m->query("
  SELECT m.id, m.match_date, m.team_home_id, m.team_away_id
  FROM matches m
  JOIN phases p ON p.id = m.phase_id
  WHERE p.tournament_id = {$tournament_id}
    AND m.status = 'agendado'
    AND m.match_date IS NOT NULL
    AND m.match_date >= NOW()
  ORDER BY m.match_date ASC
")->fetch_all(MYSQLI_ASSOC);

$forms = [];
$nextFix = [];
foreach ($teamIds as $tid) $forms[$tid] = [];

// Compute V/E/P using the custom rule’s letters (visual only)
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

// Next fixture per team
foreach ($nextRows as $nr) {
  $hid = (int)$nr['team_home_id'];
  $aid = (int)$nr['team_away_id'];
  $date = substr($nr['match_date'] ?? '', 0, 16);
  $rid = (int)$nr['id'];
  if (!isset($nextFix[$hid])) $nextFix[$hid] = ['id' => $rid, 'date' => $date, 'opp_id' => $aid];
  if (!isset($nextFix[$aid])) $nextFix[$aid] = ['id' => $rid, 'date' => $date, 'opp_id' => $hid];
}

// Opponent names/logo for next fixture display
$teamInfo = [];
if ($teamIds) {
  $res = $m->query("SELECT id,name,COALESCE(logo_url,'') AS logo_url FROM teams WHERE id IN ($teamIdsIn)");
  foreach ($res as $r) $teamInfo[(int)$r['id']] = $r;
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
  <div class="wrap">
    <div class="topbar">
      <h2 style="margin:0">Classificação — <?= h($t['name']) ?></h2>
      <form method="get" action="standings.php">
        <label>
          <!-- <select name="tournament_id" onchange="this.form.submit()">
            <?php foreach ($tournaments as $tt): ?>
              <option value="<?= (int)$tt['id'] ?>" <?= $tournament_id === (int)$tt['id'] ? 'selected' : '' ?>><?= h($tt['name']) ?></option>
            <?php endforeach; ?>
          </select> -->
        </label>
      </form>
    </div>

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
          <th>Forma</th>
          <th class="hide-sm">Próximo</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $pos = 1;
        foreach ($stand as $r):
          $tid = (int)$r['team_id'];
          $gd = (int)$r['gf'] - (int)$r['ga'];
          $logo = $r['logo_url'] ?: '/futsal-pj/assets/img/placeholder-team.png';
          $fives = $forms[$tid] ?? [];
          $next = $nextFix[$tid] ?? null;
          $oppName = $next && isset($teamInfo[$next['opp_id']]) ? $teamInfo[$next['opp_id']]['name'] : '';
          $lost = (int)$r['lost']; // shown as P
        ?>
          <tr>
            <td><?= $pos++ ?></td>
            <td>
              <div class="club">
                <img src="<?= h($logo) ?>" alt="">
                <span><?= h($r['team_name']) ?></span>
              </div>
            </td>
            <td class="hide-sm"><?= (int)$r['played'] ?></td>
            <td><?= (int)$r['won'] ?></td>
            <td><?= (int)$r['drawn'] ?></td>
            <td><?= $lost ?></td>
            <td><?= (int)$r['gf'] ?></td>
            <td><?= (int)$r['ga'] ?></td>
            <td><?= $gd ?></td>
            <td><strong><?= (int)$r['points'] ?></strong></td>
            <td>
              <div class="form">
                <?php foreach ($fives as $f): ?>
                  <a class="pill <?= h($f['result']) ?>"
                    href="/futsal-pj/match.php?id=<?= (int)$f['id'] ?>"
                    title="<?= h($f['date'] . ' • ' . $f['score']) ?>"><?= h($f['result']) ?></a>
                <?php endforeach; ?>
              </div>
            </td>
            <td class="next hide-sm">
              <?php if ($next): ?>
                <a href="/futsal-pj/match.php?id=<?= (int)$next['id'] ?>">
                  <?= h($oppName) ?> — <?= h($next['date']) ?>
                </a>
                <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>

</html>