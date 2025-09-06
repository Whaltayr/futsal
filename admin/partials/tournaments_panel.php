<?php
require_once __DIR__ . '/../../api/connection.php';
require_once __DIR__ . '/../../api/csrf.php';
$m = get_mysqli();
$res = $m->query("SELECT id,name,start_date,end_date FROM tournaments ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$token = csrf_token();
?>
<div>
  <h3>Campeonatos</h3>
  <form id="formTournament">
    <input type="hidden" name="csrf" value="<?=htmlspecialchars($token)?>">
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="id" value="">
    <label>Nome <input name="name" required></label>
    <label>Data início <input type="date" name="start_date" required></label>
    <label>Data fim <input type="date" name="end_date" required></label>
    <button type="submit">Salvar</button>
    <button type="button" id="tournCancel" style="display:none">Cancelar edição</button>
  </form>

  <h4>Lista</h4>
  <table id="tournTable">
    <thead><tr><th>ID</th><th>Nome</th><th>Início</th><th>Fim</th><th>Ações</th></tr></thead>
    <tbody>
      <?php foreach($res as $r): ?>
        <tr data-id="<?= (int)$r['id'] ?>">
          <td><?= (int)$r['id'] ?></td>
          <td><?=htmlspecialchars($r['name'])?></td>
          <td><?=htmlspecialchars($r['start_date'])?></td>
          <td><?=htmlspecialchars($r['end_date'])?></td>
          <td>
            <button class="editT" data-id="<?= (int)$r['id'] ?>">Editar</button>
            <button class="delT" data-id="<?= (int)$r['id'] ?>">Apagar</button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
