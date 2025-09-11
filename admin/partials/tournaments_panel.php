<?php
require_once __DIR__ . '/../../new-api/connection.php';
require_once __DIR__ . '/../../new-api/csrf.php';

$m = get_mysqli();
$res = $m->query("SELECT id,name,start_date,end_date FROM tournaments ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$token = csrf_token();
?>

<div class="panel panel-tournaments_panel">
  <div class="panel-header">
    <h3>Campeonatos</h3>
    <form id="formTournament">

      <div class="form-content form-grid">
        <input class="input" type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
        <input class="input" type="hidden" name="action" value="create">
        <input class="input" type="hidden" name="id" value="">
        <label>Nome <input class="input" name="name" required></label>
        <label>Data início <input class="input" type="date" name="start_date" required></label>
        <label>Data fim <input class="input" type="date" name="end_date" required></label>
      </div>
      <button class="btn btn--ok" type="submit">Salvar</button>
      <button class="btn btn--warn" type="button" id="tournCancel" style="display:none">Cancelar edição</button>
    </form>

    <h4>Lista</h4>
    <div class="table-conteiner">
      <table id="tournTable" class="table">
        <thead>
          <tr>
            <!-- <th>ID</th> -->
            <th>Nome</th>
            <th>Início</th>
            <th>Fim</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($res as $r): ?>
            <tr data-id="<?= (int)$r['id'] ?>">
              <!-- <td><?= (int)$r['id'] ?></td> -->
              <td data-cell="Nome"><?= htmlspecialchars($r['name']) ?></td>
              <td data-cell="Inicio"><?= htmlspecialchars($r['start_date']) ?></td>
              <td data-cell="Fim"><?= htmlspecialchars($r['end_date']) ?></td>
              <td data-cell="">
                <button class="editT btn btn--warn" data-id="<?= (int)$r['id'] ?>">Editar</button>
                <button class="delT btn btn--danger" data-id="<?= (int)$r['id'] ?>">Apagar</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>