<?php
require_once __DIR__ . '/../../new-api/connection.php';
require_once __DIR__ . '/../../new-api/csrf.php';

$m = get_mysqli();

$tournaments = $m->query("SELECT id,name FROM tournaments ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$phases = $m->query("
  SELECT p.id, p.tournament_id, p.name, p.type, t.name AS tourn_name
  FROM phases p
  JOIN tournaments t ON t.id = p.tournament_id
  ORDER BY p.id DESC
")->fetch_all(MYSQLI_ASSOC);

$token = csrf_token();
?>
<div class="panel panel-phases">
<div class="panel-header"> 
  <h3>Fases</h3>
  <form id="formPhase">
    <div class="form-content form-grid">
      <input  type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
      <input class="input" type="hidden" name="action" value="create">
      <input class="input" type="hidden" name="id" value="">
      <label>Torneio
        <select class="select" name="tournament_id" required>
          <option value="">— selecione —</option>
          <?php foreach ($tournaments as $t): ?>
            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Nome
        <input class="input" type="text" name="name" required placeholder="Ex.: Fase de Grupos A">
      </label>
      <label>Tipo
        <select class="select" name="type" required>
          <option value="group">Grupo</option>
          <option value="knockout">Eliminatória</option>
          <option value="final">Final</option>
        </select>
      </label>
    </div>
    <button class="btn btn--ok" type="submit">Salvar</button>
    <button class="btn btn--warn" type="button" id="phaseCancel" style="display:none">Cancelar edição</button>
  </form>

  <h4>Lista</h4>
    <div class="table-conteiner">
  <table id="phasesTable" class="table">
    <thead>
      <tr>
        <th>ID</th><th>Torneio</th><th>Nome</th><th>Tipo</th><th>Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($phases as $p): ?>
        <tr data-id="<?= (int)$p['id'] ?>" data-tournament-id="<?= (int)$p['tournament_id'] ?>">
          <td><?= (int)$p['id'] ?></td>
          <td><?= htmlspecialchars($p['tourn_name']) ?></td>
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td><?= htmlspecialchars($p['type']) ?></td>
          <td>
            <button class="editPhase btn btn--warn" data-id="<?= (int)$p['id'] ?>">Editar</button>
            <button class="delPhase btn btn--danger" data-id="<?= (int)$p['id'] ?>">Apagar</button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div>