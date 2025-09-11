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

$players = $m->query("
  SELECT p.id, p.team_id, p.name, p.number, p.position, p.dob, p.bi, p.photo_url,
         tm.name AS team_name, t.name AS tourn_name, tm.tournament_id
  FROM players p
  LEFT JOIN teams tm ON tm.id = p.team_id
  LEFT JOIN tournaments t ON t.id = tm.tournament_id
  ORDER BY p.id DESC
")->fetch_all(MYSQLI_ASSOC);

$token = csrf_token();
?>

<div class="panel panel-players">
  <div class="panel-header">
    <h3>Jogadores</h3>
    <form id="formPlayer" enctype="multipart/form-data">

      <div class="form-content form-grid">

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
        <label>Número
          <input class="input" type="text" name="number">
        </label>
        <label>Posição
          <select class="select" name="position">
            <option value="">— selecione —</option>
            <option value="Guarda-Redes">Guarda-Redes</option>
            <option value="Fixo">Fixo</option>
            <option value="Ala">Ala</option>
            <option value="Pivô">Pivô</option>
          </select>
        </label>
        <label>Data Nasc.
          <input class="input" type="date" name="dob">
        </label>
        <label>BI
          <input class="input" type="text" name="bi">
        </label>
        <label>Foto
          <input class="file" type="file" name="photo" accept="image/*">
        </label>
      </div>
      <button class="btn btn--ok" type="submit">Salvar</button>
      <button class="btn btn--warn" type="button" id="playerCancel" style="display:none">Cancelar edição</button>
    </form>
  </div>

    <h4>Lista</h4>
    <div class="table-conteiner">
      <table id="playersTable" class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Foto</th>
            <th>Nome</th>
            <th>Nº</th>
            <th>Posição</th>
            <th>Data Nasc.</th>
            <th>BI</th>
            <th>Torneio / Equipa</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($players as $p): ?>
            <tr data-id="<?= (int)$p['id'] ?>" data-team-id="<?= (int)($p['team_id'] ?? 0) ?>">
              <td data-cell="ID"><?= (int)$p['id'] ?></td>
              <td data-cell="Foto"><?php if (!empty($p['photo_url'])): ?><img src="<?= htmlspecialchars($p['photo_url']) ?>" alt="foto" style="height:32px"><?php endif; ?></td>
              <td data-cell="Nome"><?= htmlspecialchars($p['name']) ?></td>
              <td data-cell="Numero"><?= htmlspecialchars($p['number'] ?? '') ?></td>
              <td data-cell="Posiçao"><?= htmlspecialchars($p['position'] ?? '') ?></td>
              <td data-cell="Data de nascimento"><?= htmlspecialchars($p['dob'] ?? '') ?></td>
              <td data-cell="BI"><?= htmlspecialchars($p['bi'] ?? '') ?></td>
              <td data-cell="Torneio|Equipa"><?= htmlspecialchars(($p['tourn_name'] ?? '') . ' / ' . ($p['team_name'] ?? '')) ?></td>
              <td>
                <button class="editPlayer btn btn--warn" data-id="<?= (int)$p['id'] ?>">Editar</button>
                <button class="delPlayer btn btn--danger" data-id="<?= (int)$p['id'] ?>">Apagar</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>