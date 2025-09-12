<?php
require_once __DIR__ . '/../../new-api/connection.php';
$m = get_mysqli();

// tournaments for filter
$tournaments = $m->query("SELECT id, name FROM tournaments ORDER BY start_date DESC, id DESC")->fetch_all(MYSQLI_ASSOC);

// selected tournament (first one by default)
$selTid = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
if (!$selTid && $tournaments) $selTid = (int)$tournaments[0]['id'];

// fetch finalized matches for the selected tournament
$rows = [];
if ($selTid) {
    $sql = "SELECT m.id, m.match_date, m.home_score, m.away_score,
                 th.name AS home_name, th.logo_url AS home_logo,
                 ta.name AS away_name, ta.logo_url AS away_logo,
                 p.name AS phase_name
          FROM matches m
          JOIN phases p ON p.id = m.phase_id
          JOIN teams th ON th.id = m.team_home_id
          JOIN teams ta ON ta.id = m.team_away_id
          WHERE p.tournament_id = ?
            AND m.status = 'finalizado'
            AND m.home_score IS NOT NULL AND m.away_score IS NOT NULL
          ORDER BY m.match_date DESC, m.id DESC";
    $st = $m->prepare($sql);
    $st->bind_param('i', $selTid);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
}
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>
<div class="panel panel-results">
    <div class="panel-header">
        <h3>Resultados</h3>
    </div>

    <form id="resultsFilter" class="">
        <div class="form-content form-grid">
            <label>Torneio
                <select name="tournament_id" class="select">
                    <?php foreach ($tournaments as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= $selTid === (int)$t['id'] ? 'selected' : '' ?>>
                            <?= h($t['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <button class="btn btn--ok" type="submit">Filtrar</button>
    </form>

    <div class="table-conteiner">
        <table class="table" id="resultsTable">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Fase</th>
                    <th>Casa</th>
                    <th>Placar</th>
                    <th>Fora</th>
                    <th>Abrir</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr data-id="<?= (int)$r['id'] ?>">
                        <td><?= h(substr($r['match_date'] ?? '', 0, 16)) ?></td>
                        <td><?= h($r['phase_name']) ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <img src="<?= h($r['home_logo'] ?: '/futsal-pj/assets/img/placeholder-team.png') ?>" alt="" style="width:24px;height:24px;border-radius:50%;object-fit:cover;background:#eee">
                                <?= h($r['home_name']) ?>
                            </div>
                        </td>
                        <td><strong><?= (int)$r['home_score'] ?> - <?= (int)$r['away_score'] ?></strong></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <img src="<?= h($r['away_logo'] ?: '/futsal-pj/assets/img/placeholder-team.png') ?>" alt="" style="width:24px;height:24px;border-radius:50%;object-fit:cover;background:#eee">
                                <?= h($r['away_name']) ?>
                            </div>
                        </td>
                        <td>
                            <a class="btn btn--ok" href="/futsal-pj/match.php?id=<?= (int)$r['id'] ?>" target="_blank">Ver</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="6">Sem resultados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>