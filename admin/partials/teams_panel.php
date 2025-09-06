<?php
require_once __DIR__ . '/../../api/connection.php';
require_once __DIR__ . '/../../api/csrf.php';
$m = get_mysqli();
$tr = $m->query("SELECT id,name FROM tournaments ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$teams = $m->query("SELECT id,tournament_id,name,abbreviation,city,logo_url FROM teams ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$token = csrf_token();
?>
<div>
  <h3>Equipas</h3>
  <form id="formTeam" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=htmlspecialchars($token)?>">
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="id" value="">
    <label>Torneio
      <select name="tournament_id" required>
        <option value="">— selecione —</option>
        <?php foreach($tr as $t): ?>
          <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
        <?php endforeach;?>
      </select>
    </label>
    <label>Nome <input name="name" required></label>
    <label>Abreviatura <input name="abbreviation"></label>
    <label>Cidade <input name="city"></label>
    <label>Logo <input type="file" name="logo" accept="image/*"></label>
    <button type="submit">Salvar</button>
    <button type="button" id="teamCancel" style="display:none">Cancelar edição</button>
  </form>

  <h4>Lista</h4>
  <table id="teamsTable">
    <thead><tr><th>ID</th><th>Logo</th><th>Nome</th><th>Abrev</th><th>Cidade</th><th>Ações</th></tr></thead>
    <tbody>
      <?php foreach($teams as $tm): ?>
        <tr data-id="<?= (int)$tm['id'] ?>" data-tournament-id="<?= (int)$tm['tournament_id'] ?>">
          <td><?= (int)$tm['id'] ?></td>
          <td><?php if($tm['logo_url']): ?><img src="<?=htmlspecialchars($tm['logo_url'])?>" alt="logo"><?php endif;?></td>
          <td><?=htmlspecialchars($tm['name'])?></td>
          <td><?=htmlspecialchars($tm['abbreviation'])?></td>
          <td><?=htmlspecialchars($tm['city'])?></td>
          <td>
            <button class="editTeam" data-id="<?= (int)$tm['id'] ?>">Editar</button>
            <button class="delTeam" data-id="<?= (int)$tm['id'] ?>">Apagar</button>
          </td>
        </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>
