<?php
require_once __DIR__ . '/../../new-api/connection.php';
require_once __DIR__ . '/../../new-api/csrf.php';

$m = get_mysqli();

$teams = $m->query("
  SELECT tm.id, tm.name, t.name AS tourn_name
  FROM teams tm
  JOIN tournaments t ON t.id = tm.tournament_id
  ORDER BY t.name ASC, tm.name ASC
")->fetch_all(MYSQLI_ASSOC);

$staff = $m->query("
  SELECT s.id, s.team_id, s.name, s.function, s.contact, s.photo_url,
         tm.name AS team_name, t.name AS tourn_name, tm.tournament_id
  FROM staff s
  LEFT JOIN teams tm ON tm.id = s.team_id
  LEFT JOIN tournaments t ON t.id = tm.tournament_id
  ORDER BY s.id DESC
")->fetch_all(MYSQLI_ASSOC);

$token = csrf_token();
?>
<div class="panel panel-staff">
  <div class="panel-header">
    <h3>Staff</h3>
    <form id="formStaff" enctype="multipart/form-data">

      <div class="form-grid">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="id" value="">
        <label>Equipa
          <select class="select" name="team_id" required>
            <option value="">— selecione —</option>
            <?php foreach ($teams as $tm): ?>
              <option value="<?= (int)$tm['id'] ?>"><?= htmlspecialchars($tm['tourn_name'] . ' — ' . $tm['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Nome
          <input class="input" type="text" name="name" required>
        </label>
        <label>Função
          <select class="select" name="function">
            <option value="">— selecione —</option>
            <option value="Treinador">Treinador</option>
            <option value="Adjunto">Adjunto</option>
            <option value="Preparador Físico">Preparador Físico</option>
            <option value="Médico">Médico</option>
            <option value="Fisioterapeuta">Fisioterapeuta</option>
            <option value="Team Manager">Team Manager</option>
            <option value="Delegado">Delegado</option>
          </select>
        </label>
        <label>Contacto
          <input class="input" type="text" name="contact">
        </label>
        <label>Foto
          <input class="file" type="file" name="photo" accept="image/*">
        </label>
      </div>
      <button class="btn btn--ok" type="submit">Salvar</button>
      <button class="btn btn--warn" type="button" id="staffCancel" style="display:none">Cancelar edição</button>
    </form>

    <h4>Lista</h4>
    <div class="table-conteiner">
      <table id="staffTable" class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Foto</th>
            <th>Nome</th>
            <th>Função</th>
            <th>Contacto</th>
            <th>Torneio / Equipa</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($staff as $s): ?>
            <tr data-id="<?= (int)$s['id'] ?>" data-team-id="<?= (int)($s['team_id'] ?? 0) ?>">
              <td data-cell="ID"><?= (int)$s['id'] ?></td>
              <td data-cell="Foto"><?php if (!empty($s['photo_url'])): ?><img src="<?= htmlspecialchars($s['photo_url']) ?>" alt="foto" style="height:32px"><?php endif; ?></td>
              <td data-cell="Nome"><?= htmlspecialchars($s['name']) ?></td>
              <td data-cell="Funçao"><?= htmlspecialchars($s['function'] ?? '') ?></td>
              <td data-cell="Contacto"><?= htmlspecialchars($s['contact'] ?? '') ?></td>
              <td data-cell="Torneio|Equipa"><?= htmlspecialchars(($s['tourn_name'] ?? '') . ' / ' . ($s['team_name'] ?? '')) ?></td>
              <td>
                <button class="btn btn--warn editStaff" data-id="<?= (int)$s['id'] ?>">Editar</button>
                <button class="btn btn--danger delStaff" data-id="<?= (int)$s['id'] ?>">Apagar</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>