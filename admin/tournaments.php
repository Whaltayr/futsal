<?php
// admin/tournaments.php
declare(strict_types=1);
require_once __DIR__ . '/../new-api/auth_check.php';
require_once __DIR__ . '/../new-api/connection.php';

$action = $_GET['a'] ?? 'list';

// Carregar 1 reg para edição
$edit = null;
if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $mysqli->prepare("SELECT id, name, start_date, end_date FROM tournaments WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $edit = $stmt->get_result()->fetch_assoc();
    }
}

// Lista
$stmt = $mysqli->prepare("SELECT id, name, start_date, end_date, created_at FROM tournaments ORDER BY id DESC");
$stmt->execute();
$tournaments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="pt"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Painel — Campeonatos</title>
<link rel="stylesheet" href="/futsal/futsal-pj/assets/css/admin.css">
</head><body>
<h1>Campeonatos</h1>

<section>
  <h2><?= $edit ? 'Editar Campeonato' : 'Novo Campeonato' ?></h2>
  <form method="post" action="/futsal/futsal-pj/api/tournaments_<?= $edit ? 'update' : 'create' ?>.php">
    <?php if ($edit): ?>
      <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
    <?php endif; ?>
    <label>Nome
      <input type="text" name="name" required value="<?= htmlspecialchars($edit['name'] ?? '', ENT_QUOTES) ?>">
    </label>
    <label>Data início
      <input type="date" name="start_date" required value="<?= htmlspecialchars($edit['start_date'] ?? '', ENT_QUOTES) ?>">
    </label>
    <label>Data fim
      <input type="date" name="end_date" required value="<?= htmlspecialchars($edit['end_date'] ?? '', ENT_QUOTES) ?>">
    </label>
    <button type="submit"><?= $edit ? 'Guardar alterações' : 'Criar' ?></button>
    <?php if ($edit): ?><a href="/futsal/futsal-pj/admin/tournaments.php">Cancelar</a><?php endif; ?>
  </form>
</section>

<section>
  <h2>Lista</h2>
  <table border="1" cellpadding="6">
    <thead><tr><th>ID</th><th>Nome</th><th>Início</th><th>Fim</th><th>Criado</th><th>Ações</th></tr></thead>
    <tbody>
      <?php foreach ($tournaments as $t): ?>
        <tr>
          <td><?= (int)$t['id'] ?></td>
          <td><?= htmlspecialchars($t['name']) ?></td>
          <td><?= htmlspecialchars($t['start_date']) ?></td>
          <td><?= htmlspecialchars($t['end_date']) ?></td>
          <td><?= htmlspecialchars($t['created_at']) ?></td>
          <td>
            <a href="/futsal/futsal-pj/admin/tournaments.php?a=edit&id=<?= (int)$t['id'] ?>">Editar</a>
            <form action="/futsal/futsal-pj/api/tournaments_delete.php" method="post" style="display:inline" onsubmit="return confirm('Apagar este campeonato?');">
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <button type="submit">Apagar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
</body></html>
