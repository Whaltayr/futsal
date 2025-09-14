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


// Last 5 finished matches for the current tournament
$lastGames = [];
$stLG = $m->prepare("
  SELECT
    m.id,
    m.match_date,
    th.name        AS home_name,
    ta.name        AS away_name,
    COALESCE(th.logo_url, '') AS home_logo,
    COALESCE(ta.logo_url, '') AS away_logo,
    m.home_score,
    m.away_score,
    p.name         AS phase_name
  FROM matches m
  JOIN phases p      ON p.id = m.phase_id
  JOIN tournaments t ON t.id = p.tournament_id
  JOIN teams th      ON th.id = m.team_home_id
  JOIN teams ta      ON ta.id = m.team_away_id
  WHERE t.id = ?
    AND m.status = 'agendado'
    AND m.home_score IS NOT NULL
    AND m.away_score IS NOT NULL
    AND m.match_date IS NOT NULL
  ORDER BY m.match_date DESC, m.id DESC
  LIMIT 5
");
$stLG->bind_param('i', $tournament_id);
$stLG->execute();
$lastGames = $stLG->get_result()->fetch_all(MYSQLI_ASSOC);
$stLG->close();
?>

<!doctype html>
<html lang="pt">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/css/standings.css">
  <link rel="shortcut icon" href="assets/img/fav.png" type="image/x-icon">
  <title>Classificação — <?= h($t['name']) ?></title>
</head>
<!-- <style>

</style> -->


<body>
  <header>
    <section class="top-section-header">
      <div class="last-games" style="max-width:none; height:auto;">
        <div class="texts" style="justify-content:flex-start; gap:1rem; padding:.6rem 1rem;">
          <h2 class="txtlast">Últimos Jogos</h2>
        </div>

        <div class="last-games-row" style="padding: 0 1rem 1rem;">
          <?php if (empty($lastGames)): ?>
            <div class="game-card">Sem jogos finalizados.</div>
          <?php else: ?>
            <?php foreach ($lastGames as $g):
              $date = $g['match_date'] ? date('Y-m-d H:i', strtotime($g['match_date'])) : '—';
              $homeLogo = $g['home_logo'] ?: '/futsal-pj/assets/img/placeholder-team.png';
              $awayLogo = $g['away_logo'] ?: '/futsal-pj/assets/img/placeholder-team.png';
            ?>
              <a class="game-card" href="/futsal-pj/match.php?id=<?= (int)$g['id'] ?>" title="<?= h($g['phase_name'] ?? '') ?>">
                <div class="gc-header">
                  <span class="gc-date"><?= h($date) ?></span>
                  <span class="gc-phase"><?= h($g['phase_name'] ?? '') ?></span>
                </div>

                <div class="gc-teams">
                  <div class="gc-side">
                    <img class="gc-logo" src="<?= h($homeLogo) ?>" alt="">
                    <span class="gc-name"><?= h($g['home_name']) ?></span>
                  </div>
                  <div class="gc-score">
                    <?= (int)$g['home_score'] ?> - <?= (int)$g['away_score'] ?>
                  </div>
                  <div class="gc-side gc-right">
                    <span class="gc-name"><?= h($g['away_name']) ?></span>
                    <img class="gc-logo" src="<?= h($awayLogo) ?>" alt="">
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section class="middle-section-header">
      <nav>
        <div class="logo"><a href="index.php"><img src="assets/img/logo.jpeg" alt=""></a></div>
        <div class="links">
          <div class="select-wrap">
            <select id="selectStandings" aria-label="Selecionar classificação">
              <option value="tournament" selected>Classificação do torneio</option>
              <option value="A">Classificação Grupo A</option>
              <option value="B">Classificação Grupo B</option>
            </select>
          </div>
          <div class="select-wrap">
            <select id="selectResults" aria-label="Selecionar classificação">
              <option value="tournament" selected>Resultados torneio</option>
              <option value="A">Resultados Grupo A</option>
              <option value="B">Resultados Grupo B</option>
            </select>
          </div>
        </div>
      </nav>
    </section>

    <section class="bottom-section-header overlay"></section>
  </header>



  <main>
    <div class="wrap">
      <div class="topbar">
        <h2 style="margin:0">Classificação — <?= h($t['name']) ?></h2>
        <!-- <form method="get" action="standings.php">
          <label>
            <select name="tournament_id" onchange="this.form.submit()">
              <?php foreach ($tournaments as $tt): ?>
                <option value="<?= (int)$tt['id'] ?>" <?= $tournament_id === (int)$tt['id'] ? 'selected' : '' ?>><?= h($tt['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </form> -->
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
                <td data-cell="Pos"><?= $pos++ ?></td>
                <td data-cell="Clube">
                  <div class="club">
                    <img src="<?= h($logo) ?>" alt="">
                    <span><?= h($r['team_name']) ?></span>
                  </div>
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
                      <a class="pill <?= h($f['result']) ?>"
                        href="/futsal-pj/match.php?id=<?= (int)$f['id'] ?>"
                        title="<?= h($f['date'] . ' • ' . $f['score']) ?>"><?= h($f['result']) ?></a>
                    <?php endforeach; ?>
                  </div>
                </td>
                <td class="next hide-sm" data-cell="Próximo">
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

    </div>
    <div class="wrap">
      <div class="topbar">
        <h2 style="margin:0">Classificação — <?= h($t['name']) ?></h2>
        <!-- <form method="get" action="standings.php">
          <label>
            <select name="tournament_id" onchange="this.form.submit()">
              <?php foreach ($tournaments as $tt): ?>
                <option value="<?= (int)$tt['id'] ?>" <?= $tournament_id === (int)$tt['id'] ? 'selected' : '' ?>><?= h($tt['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </form> -->
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
                <td data-cell="Pos"><?= $pos++ ?></td>
                <td data-cell="Clube">
                  <div class="club">
                    <img src="<?= h($logo) ?>" alt="">
                    <span><?= h($r['team_name']) ?></span>
                  </div>
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
                      <a class="pill <?= h($f['result']) ?>"
                        href="/futsal-pj/match.php?id=<?= (int)$f['id'] ?>"
                        title="<?= h($f['date'] . ' • ' . $f['score']) ?>"><?= h($f['result']) ?></a>
                    <?php endforeach; ?>
                  </div>
                </td>
                <td class="next hide-sm" data-cell="Próximo">
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

    </div>
    <div class="wrap">
      <div class="topbar">
        <h2 style="margin:0">Classificação — <?= h($t['name']) ?></h2>
        <!-- <form method="get" action="standings.php">
          <label>
            <select name="tournament_id" onchange="this.form.submit()">
              <?php foreach ($tournaments as $tt): ?>
                <option value="<?= (int)$tt['id'] ?>" <?= $tournament_id === (int)$tt['id'] ? 'selected' : '' ?>><?= h($tt['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </form> -->
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
                <td data-cell="Pos"><?= $pos++ ?></td>
                <td data-cell="Clube">
                  <div class="club">
                    <img src="<?= h($logo) ?>" alt="">
                    <span><?= h($r['team_name']) ?></span>
                  </div>
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
                      <a class="pill <?= h($f['result']) ?>"
                        href="/futsal-pj/match.php?id=<?= (int)$f['id'] ?>"
                        title="<?= h($f['date'] . ' • ' . $f['score']) ?>"><?= h($f['result']) ?></a>
                    <?php endforeach; ?>
                  </div>
                </td>
                <td class="next hide-sm" data-cell="Próximo">
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

    </div>
  </main>

</body>

</html>