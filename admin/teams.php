<?php
// admin/teams.php
declare(strict_types=1);
require_once __DIR__ . '/../new-api/auth_check.php';
require_once __DIR__ . '/../new-api/connection.php';

// Carregar torneios para o <select>
$stmt = $mysqli->prepare("SELECT id, name FROM tournaments ORDER BY id DESC");
$stmt->execute();
$tournaments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$action = $_GET['a'] ?? 'list';
$edit = null;
if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id>0) {
        $stmt = $mysqli->prepare("SELECT id, tournament_id, name, abbreviation, city, logo_url FROM teams WHERE id=?");
        $stmt->bind_param('i',$id);
        $stmt->execute();
        $edit = $stmt->get_result()->fetch_assoc();
    }
}

// Lista equipas
$q = "SELECT t.id, t.name, t.abbreviation, t.city, t.logo_url, tr.name as tournament_name
      FROM teams t JOIN tournaments tr ON tr.id=t.tournament_id
      ORDER BY t.id DESC";
$stmt = $mysqli->prepare($q);
$stmt->execute();
$teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="pt"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Painel — Equipas</title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head><body>
<h1>Equipas</h1>

<section>
  <h2><?= $edit ? 'Editar Equipa' : 'Nova Equipa' ?></h2>
  <form method="post" enctype="multipart/form-data" action="/futsal-pj/api/teams_<?= $edit ? 'update' : 'create' ?>.php">
    <?php if ($edit): ?>
      <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
      <?php if (!empty($edit['logo_url'])): ?>
        <p>Logo atual: <img src="<?= htmlspecialchars($edit['logo_url']) ?>" alt="" style="height:36px"></p>
      <?php endif; ?>
    <?php endif; ?>

    <label>Torneio
      <select name="tournament_id" required>
        <option value="">— selecione —</option>
        <?php foreach ($tournaments as $tr): ?>
          <option value="<?= (int)$tr['id'] ?>" <?= ($edit && (int)$edit['tournament_id']===(int)$tr['id'])?'selected':'' ?>>
            <?= htmlspecialchars($tr['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Nome da equipa
      <input type="text" name="name" required value="<?= htmlspecialchars($edit['name'] ?? '', ENT_QUOTES) ?>">
    </label>

    <label>Abreviatura
      <input type="text" name="abbreviation" maxlength="10" value="<?= htmlspecialchars($edit['abbreviation'] ?? '', ENT_QUOTES) ?>">
    </label>

    <label>Cidade
      <input type="text" name="city" value="<?= htmlspecialchars($edit['city'] ?? '', ENT_QUOTES) ?>">
    </label>

    <label>Logo (ficheiro imagem)<?= $edit ? ' — enviar novo para substituir' : '' ?>
      <input type="file" name="logo" accept=".jpg,.jpeg,.png,.gif,.webp" <?= $edit ? '' : 'required' ?>>
    </label>

    <button type="submit"><?= $edit ? 'Guardar alterações' : 'Criar equipa' ?></button>
    <?php if ($edit): ?><a href="/futsal-pj/admin/teams.php">Cancelar</a><?php endif; ?>
  </form>
</section>

<section>
  <h2>Lista</h2>
  <table border="1" cellpadding="6">
    <thead><tr><th>ID</th><th>Logo</th><th>Equipa</th><th>Abrev.</th><th>Cidade</th><th>Torneio</th><th>Ações</th></tr></thead>
    <tbody>
      <?php foreach ($teams as $tm): ?>
        <tr>
          <td><?= (int)$tm['id'] ?></td>
          <td><?php if (!empty($tm['logo_url'])): ?><img src="<?= htmlspecialchars($tm['logo_url']) ?>" alt="" style="height:32px"><?php endif; ?></td>
          <td><?= htmlspecialchars($tm['name']) ?></td>
          <td><?= htmlspecialchars($tm['abbreviation']) ?></td>
          <td><?= htmlspecialchars($tm['city']) ?></td>
          <td><?= htmlspecialchars($tm['tournament_name']) ?></td>
          <td>
            <a href="/futsal-pj/admin/teams.php?a=edit&id=<?= (int)$tm['id'] ?>">Editar</a>
            <form action="/futsal-pj/api/teams_delete.php" method="post" style="display:inline" onsubmit="return confirm('Apagar esta equipa?');">
              <input type="hidden" name="id" value="<?= (int)$tm['id'] ?>">
              <button type="submit">Apagar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
</body></html>
