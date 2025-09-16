<?php
require_once __DIR__ . '/../../new-api/connection.php';
require_once __DIR__ . '/../../new-api/csrf.php';

$m = get_mysqli();

$tournaments = $m->query("SELECT id,name FROM tournaments ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$phases = $m->query("SELECT id,tournament_id,name,type FROM phases ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$teams = $m->query("SELECT id,tournament_id,name FROM teams ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

$matches = $m->query("
  SELECT mt.id, mt.phase_id, mt.team_home_id, mt.team_away_id,
         mt.match_date, mt.round, mt.home_score, mt.away_score, mt.status,
         ph.tournament_id, ph.name AS phase_name, ph.type AS phase_type,
         th.name AS home_name, ta.name AS away_name, tt.name AS tourn_name
  FROM matches mt
  JOIN phases ph ON ph.id = mt.phase_id
  JOIN tournaments tt ON tt.id = ph.tournament_id
  JOIN teams th ON th.id = mt.team_home_id
  JOIN teams ta ON ta.id = mt.team_away_id
  ORDER BY mt.match_date DESC, mt.id DESC
")->fetch_all(MYSQLI_ASSOC);

$token = csrf_token();
?>

<div class="panel panel-matches">
  <div class="panel-header">
    <h3>Jogos</h3>
    <span class="desc">Gerir jogos, resultados e estado</span>
    <form id="formMatch" autocomplete="off">
      <div class="form-content form-grid">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="match_date" value="">
        <input type="hidden" name="id" value="">
        <label>Torneio
          <select class="select" name="tournament_id" id="matchTournament" required autocomplete="off">
            <option value="">— selecione —</option>
            <?php foreach ($tournaments as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Fase
          <select class="select" name="phase_id" id="matchPhase" required autocomplete="off">
            <option value="">— selecione —</option>
            <?php foreach ($phases as $p): ?>
              <option data-tourn="<?= (int)$p['tournament_id'] ?>" value="<?= (int)$p['id'] ?>">
                <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['type']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Mandante
          <select class="select" name="team_home_id" id="homeTeam" required autocomplete="off">
            <option value="">— selecione —</option>
            <?php foreach ($teams as $tm): ?>
              <option data-tourn="<?= (int)$tm['tournament_id'] ?>" value="<?= (int)$tm['id'] ?>">
                <?= htmlspecialchars($tm['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Visitante
          <select class="select" name="team_away_id" id="awayTeam" required autocomplete="off">
            <option value="">— selecione —</option>
            <?php foreach ($teams as $tm): ?>
              <option data-tourn="<?= (int)$tm['tournament_id'] ?>" value="<?= (int)$tm['id'] ?>">
                <?= htmlspecialchars($tm['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>Data
          <input class="input" type="date" name="match_date_date"
            value="<?= !empty($match['match_date']) ? htmlspecialchars(date('Y-m-d', strtotime($match['match_date']))) : '' ?>"
            required></label>

        <label>Hora
          <input class="input" type="time" name="match_date_time"
            value="<?= !empty($match['match_date']) ? htmlspecialchars(date('H:i', strtotime($match['match_date']))) : '' ?>"
            required></label>

        <label>Rodada
          <input class="input" type="text" name="round" placeholder="ex.: 1ª rodada" autocomplete="off">
        </label>
        <label>Gols Mandante
          <input class="input gol_input" type="number" name="home_score" min="0" step="1" value="0" autocomplete="off" inputmode="numeric">
        </label>
        <label>Gols Visitante
          <input class="input gol_input" type="number" name="away_score" min="0" step="1" value="0" autocomplete="off" inputmode="numeric">
        </label>
        <label>Status
          <select class="select" name="status" required autocomplete="off">
            <option value="agendado">Agendado</option>
            <option value="decorrendo">Decorrendo</option>
            <option value="finalizado">Finalizado</option>
          </select>
        </label>
      </div>

      <button class="btn btn--ok" type="submit">Salvar</button>
      <button class="btn btn--warn" type="button" id="matchCancel" style="display:none">Cancelar edição</button>
    </form>

    <h4>Lista</h4>
    <div class="table-conteiner">
      <table id="matchesTable" class="table">
        <thead>
          <tr>
            <!-- <th>ID</th> -->
            <th>Torneio</th>
            <th>Fase</th>
            <th>Data/Hora</th>
            <th>Mandante</th>
            <th></th>
            <th>Visitante</th>
            <th>Rodada</th>
            <th>Status</th>
            <th>Placar</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($matches as $mt): ?>
            <tr data-id="<?= (int)$mt['id'] ?>"
              data-tournament-id="<?= (int)$mt['tournament_id'] ?>"
              data-phase-id="<?= (int)$mt['phase_id'] ?>"
              data-home-id="<?= (int)$mt['team_home_id'] ?>"
              data-away-id="<?= (int)$mt['team_away_id'] ?>">
              <!-- <td data-cell="ID"><?= (int)$mt['id'] ?></td> -->
              <td data-cell="Torneio"><?= htmlspecialchars($mt['tourn_name']) ?></td>
              <td data-cell="Fase"><?= htmlspecialchars($mt['phase_name']) ?> (<?= htmlspecialchars($mt['phase_type']) ?>)</td>
              <td data-cell="Data/Hora"><?= htmlspecialchars(substr(str_replace(' ', 'T', $mt['match_date']), 0, 16)) ?></td>
              <td data-cell="Mandante"><?= htmlspecialchars($mt['home_name']) ?></td>
              <td data-cell="">VS</td>
              <td data-cell="Visitante"><?= htmlspecialchars($mt['away_name']) ?></td>
              <td data-cell="Rodada"><?= htmlspecialchars($mt['round'] ?? '') ?></td>
              <?php
              $st = $mt['status'] ?? 'agendado';
              $clsKey = $st === 'finalizado' ? 'finalizado' : ($st === 'decorrendo' ? 'decorrendo' : 'agendado');
              ?>
              <td data-cell="Status">
                <span class="badge badge-status is-<?= $clsKey ?>">
                  <?= htmlspecialchars($st) ?>
                </span>
              </td>
              <td data-cell="Placar"><?= (int)$mt['home_score'] ?> - <?= (int)$mt['away_score'] ?></td>
              <td data-cell="">
                <button class="editMatch btn btn--warn" data-id="<?= (int)$mt['id'] ?>">Editar</button>
                <button class="delMatch btn btn--danger" data-id="<?= (int)$mt['id'] ?>">Apagar</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  (function() {
    const tournSel = document.getElementById('matchTournament');
    const phaseSel = document.getElementById('matchPhase');
    const homeSel = document.getElementById('homeTeam');
    const awaySel = document.getElementById('awayTeam');

    function filterByTournament() {
      const t = tournSel.value;
      [phaseSel, homeSel, awaySel].forEach(sel => {
        [...sel.options].forEach(opt => {
          if (!opt.value) return;
          opt.hidden = t && opt.dataset.tourn !== t;
        });
      });
    }
    tournSel?.addEventListener('change', filterByTournament);
    filterByTournament();
  })();
</script>