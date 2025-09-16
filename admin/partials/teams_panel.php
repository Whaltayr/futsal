<?php
require_once __DIR__ . '/../../new-api/connection.php';
require_once __DIR__ . '/../../new-api/auth_check.php';
require_once __DIR__ . '/../../new-api/csrf.php';

$m = get_mysqli();
$m->set_charset('utf8mb4');

$tr = $m->query("SELECT id,name FROM tournaments ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$teams = $m->query("
    SELECT t.id, t.tournament_id, tr.name AS tournament_name,
           t.name, t.abbreviation, t.group_label, t.city, t.logo_url
    FROM teams t
    JOIN tournaments tr ON tr.id = t.tournament_id
    ORDER BY t.id DESC
")->fetch_all(MYSQLI_ASSOC);

$token = csrf_token();
$BASE = rtrim(str_replace('\\','/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/'); // ex.: /futsal-pj
?>
<div class="panel panel-teams">
  <div class="panel-header">
    <h3>Equipas</h3>

    <form id="formTeam" class="form" enctype="multipart/form-data" autocomplete="off">
      <div class="form-grid">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="id" value="">
        <input type="hidden" name="logo_url" value="">

        <label>Torneio
          <select class="select" name="tournament_id" required>
            <option value="">— selecione —</option>
            <?php foreach ($tr as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>Nome
          <input class="input" name="name" required>
        </label>

        <label>Abreviatura
          <input class="input" name="abbreviation" maxlength="10">
        </label>

        <label>Grupo
          <select class="select" name="group_label" required>
            <option value="A" selected>Grupo A</option>
            <option value="B">Grupo B</option>
          </select>
        </label>

        <label>Cidade
          <input class="input" name="city">
        </label>

        <label>Logo (upload)
          <input class="file" type="file" name="logo" id="logo_file" accept="image/*">
          <small id="logo_status" style="display:block;color:#666;margin-top:4px;">Nenhum ficheiro selecionado.</small>
          <div style="margin-top:6px;">
            <img id="logo_preview" src="" alt="" style="display:none; height:48px;">
          </div>
        </label>
      </div>

      <div class="form-actions" style="margin-top:8px; display:flex; gap:8px;">
        <button class="btn btn--ok" type="submit">Salvar</button>
        <button class="btn btn--warn" type="button" id="teamCancel" style="display:none">Cancelar edição</button>
      </div>
    </form>

    <h4 style="margin-top:24px;">Lista</h4>
    <div class="table-conteiner">
      <table id="teamsTable" class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Logo</th>
            <th>Nome</th>
            <th>Abrev</th>
            <th>Grupo</th>
            <th>Cidade</th>
            <th>Torneio</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($teams as $tm): ?>
            <tr data-id="<?= (int)$tm['id'] ?>" data-tournament-id="<?= (int)$tm['tournament_id'] ?>">
              <td data-cell="ID"><?= (int)$tm['id'] ?></td>
              <td data-cell="Logo">
                <?php if (!empty($tm['logo_url'])): ?>
                  <img src="<?= htmlspecialchars($tm['logo_url']) ?>" alt="logo" style="height:32px">
                <?php else: ?>—<?php endif; ?>
              </td>
              <td data-cell="Nome"><?= htmlspecialchars($tm['name']) ?></td>
              <td data-cell="Abreviatura"><?= htmlspecialchars($tm['abbreviation'] ?? '') ?></td>
              <td data-cell="Grupo"><?= htmlspecialchars($tm['group_label'] ?? '') ?></td>
              <td data-cell="Cidade"><?= htmlspecialchars($tm['city'] ?? '') ?></td>
              <td data-cell="Torneio"><?= htmlspecialchars($tm['tournament_name']) ?></td>
              <td>
                <button class="btn btn--warn editTeam" data-id="<?= (int)$tm['id'] ?>">Editar</button>
                <button class="btn btn--danger delTeam" data-id="<?= (int)$tm['id'] ?>">Apagar</button>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($teams)): ?>
            <tr><td colspan="8">Sem equipas.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function(){
  const form = document.getElementById('formTeam');
  const table = document.getElementById('teamsTable');
  const btnCancel = document.getElementById('teamCancel');
  const logoFile = document.getElementById('logo_file');
  const logoStatus = document.getElementById('logo_status');
  const logoPreview = document.getElementById('logo_preview');

  const BASE = "<?= $BASE ?>";
  const API_TEAMS  = `${BASE}/new-api/teams_actions.php`;

  function setModeCreate() {
    form.action.value = 'create';
    form.id.value = '';
    btnCancel.style.display = 'none';
  }
  function setModeEdit() {
    form.action.value = 'update';
    btnCancel.style.display = 'inline-block';
  }

  logoFile.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) {
      logoStatus.textContent = 'Nenhum ficheiro selecionado.';
      logoPreview.style.display = 'none';
      logoPreview.src = '';
      return;
    }
    logoStatus.textContent = `Selecionado: ${file.name} (${Math.round(file.size/1024)} KB)`;
    const reader = new FileReader();
    reader.onload = (ev) => {
      logoPreview.src = ev.target.result;
      logoPreview.style.display = 'inline-block';
    };
    reader.readAsDataURL(file);
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    try {
      const res = await fetch(API_TEAMS, { method: 'POST', body: fd, credentials: 'same-origin' });
      const j = await res.json().catch(()=>({ok:false,erro:'JSON inválido'}));
      if (!res.ok || !j.ok) throw new Error(j.erro || ('HTTP '+res.status));
      // Reload somente o partial atual (se tiver loader global) ou fallback:
      window.location.reload();
    } catch (err) {
      alert('Erro ao salvar: ' + err.message);
    }
  });

  btnCancel.addEventListener('click', () => {
    form.reset();
    logoPreview.style.display = 'none';
    logoPreview.src = '';
    setModeCreate();
  });

  table.addEventListener('click', async (e) => {
    const editBtn = e.target.closest('.editTeam');
    const delBtn = e.target.closest('.delTeam');
    if (!editBtn && !delBtn) return;
    const tr = e.target.closest('tr[data-id]');
    if (!tr) return;
    const id = tr.getAttribute('data-id');
    const cells = tr.querySelectorAll('td');

    if (editBtn) {
      setModeEdit();
      form.id.value = id;
      form.tournament_id.value = tr.getAttribute('data-tournament-id');
      form.name.value = cells[2].textContent.trim();
      form.abbreviation.value = cells[3].textContent.trim();
      form.group_label.value = cells[4].textContent.trim() || 'A';
      form.city.value = cells[5].textContent.trim();

      const img = tr.querySelector('td[data-cell="Logo"] img');
      if (img) {
        logoPreview.src = img.getAttribute('src');
        logoPreview.style.display = 'inline-block';
      } else {
        logoPreview.style.display = 'none';
        logoPreview.src = '';
      }
      if (logoFile) logoFile.value = '';
      form.scrollIntoView({behavior:'smooth'});
      return;
    }

    if (delBtn) {
      if (!confirm('Apagar equipa?')) return;
      const fd = new FormData();
      fd.append('csrf_token', form.csrf_token.value);
      fd.append('action', 'delete');
      fd.append('id', id);
      try {
        const res = await fetch(API_TEAMS, { method: 'POST', body: fd, credentials: 'same-origin' });
        const j = await res.json().catch(()=>({ok:false,erro:'JSON inválido'}));
        if (!res.ok || !j.ok) throw new Error(j.erro || ('HTTP '+res.status));
        window.location.reload();
      } catch (err) {
        alert('Erro: ' + err.message);
      }
    }
  });

  setModeCreate();
})();
</script>