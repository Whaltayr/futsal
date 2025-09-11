<?php
require_once __DIR__ . '/../../new-api/connection.php';
require_once __DIR__ . '/../../new-api/csrf.php';

$m = get_mysqli();
$tr = $m->query("SELECT id,name FROM tournaments ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$teams = $m->query("SELECT id,tournament_id,name,abbreviation,city,logo_url FROM teams ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$token = csrf_token();
?>
<div class="panel panel-teams">
  <div class="panel-header">
    <h3>Equipas</h3>
    <form id="formTeam" enctype="multipart/form-data">

      <div class="form-grid">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="id" value="">
        <label>Torneio
          <select class="select" name="tournament_id" required>
            <option value="">— selecione —</option>
            <?php foreach ($tr as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Nome <input class="input" name="name" required></label>
        <label>Abreviatura <input class="input" name="abbreviation"></label>
        <label>Cidade <input class="input" name="city"></label>
        <label>Logo <input class="file" type="file" name="logo" accept="image/*"></label>
      </div>
      <button class="btn btn--ok" type="submit">Salvar</button>
      <button class="btn btn--warn" type="button" id="teamCancel" style="display:none">Cancelar edição</button>
    </form>

    <h4>Lista</h4>
    <div class="table-conteiner">
      <table id="teamsTable" class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Logo</th>
            <th>Nome</th>
            <th>Abrev</th>
            <th>Cidade</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($teams as $tm): ?>
            <tr data-id="<?= (int)$tm['id'] ?>" data-tournament-id="<?= (int)$tm['tournament_id'] ?>">
              <td data-cell="ID"><?= (int)$tm['id'] ?></td>
              <td data-cell="Logo"><?php if ($tm['logo_url']): ?><img src="<?= htmlspecialchars($tm['logo_url']) ?>" alt="logo" style="height:32px"><?php endif; ?></td>
              <td data-cell="Nome"><?= htmlspecialchars($tm['name']) ?></td>
              <td data-cell="Abreviatura"><?= htmlspecialchars($tm['abbreviation']) ?></td>
              <td data-cell="Cidade"><?= htmlspecialchars($tm['city']) ?></td>
              <td>
                <button class="btn btn--warn editTeam" data-id="<?= (int)$tm['id'] ?>">Editar</button>
                <button class="btn btn--danger delTeam" data-id="<?= (int)$tm['id'] ?>">Apagar</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>