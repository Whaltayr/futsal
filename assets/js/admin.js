// assets/js/admin.js
// Loader simples de parciais + handlers CRUD para campeonatos, equipas, jogadores, staff e jogos.
// Requer que o <body> tenha data-base, data-api e data-partials configurados no ad_panel.php.

/* Toast messages */
/* Toast messages */
/* Toast messages (robusto) */
(function () {
  function ensureToastRoot() {
    // garante um único root sempre no document.body
    let root = document.querySelector('.toasts');
    if (!root) {
      root = document.createElement('div');
      root.className = 'toasts';
      root.setAttribute('aria-live', 'polite');
      root.setAttribute('aria-atomic', 'true');
      // garante append no body quando for seguro
      (document.body || document.documentElement).appendChild(root);
    }
    return root;
  }

  function makeToastElement({ title, msg, type }) {
    const el = document.createElement('div');
    el.className = `toast toast--${type}`;
    el.setAttribute('role', type === 'err' ? 'alert' : 'status');
    el.innerHTML = `<div class="title">${title}</div><div class="msg">${msg}</div>`;
    // estilos iniciais para animação
    el.style.opacity = '0';
    el.style.transition = 'opacity .22s ease, transform .22s ease';
    el.style.transform = 'translateY(6px)';
    return el;
  }

  function showToast({ title = 'Info', msg = '', type = 'info', timeout = 3500 } = {}) {
    try {
      const root = ensureToastRoot();
      const el = makeToastElement({ title, msg, type });
      root.appendChild(el);

      // forçar reflow para animação
      requestAnimationFrame(() => {
        el.style.opacity = '1';
        el.style.transform = 'translateY(0)';
      });

      const close = () => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(6px)';
        setTimeout(() => { try { el.remove(); } catch {} }, 260);
      };

      // auto-close
      const tId = setTimeout(close, timeout);
      // click to close
      el.addEventListener('click', () => { clearTimeout(tId); close(); });

      // accessibility: focus briefly so screenreaders notice
      el.tabIndex = -1;
      el.focus({ preventScroll: true });
    } catch (err) {
      // se tudo falhar, loga para console mas não quebre a app
      console.error('showToast failed', err);
    }
  }

  // expõe globalmente
  window.showToast = showToast;
})();



(function () {
  console.log('[admin.js] loaded');

  const content = document.getElementById("panelContent");
  const BASE = document.body.dataset.base || "";
  const API = document.body.dataset.api || BASE + "/new-api";
  const PARTIALS = document.body.dataset.partials || BASE + "/admin/partials";

  // Helper de requests
  async function request(url, opts = {}) {
    const res = await fetch(url, {
      credentials: "same-origin",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        ...(opts.headers || {}),
      },
      ...opts,
    });
    const ct = res.headers.get("content-type") || "";
    const isJSON = ct.includes("application/json");
    let body;
    try {
      body = isJSON ? await res.json() : await res.text();
    } catch {
      body = await res.text();
    }
    if (!res.ok) {
      const detail = isJSON ? body?.erro || JSON.stringify(body) : body;
      throw new Error(`HTTP ${res.status} — ${String(detail).slice(0, 500)}`);
    }
    return body;
  }

  // Carrega uma aba/partial
  async function loadTab(name) {
    const loading = document.getElementById("loading"); // referência atual
    if (loading) loading.style.display = "";
    console.log('[admin.js] loadTab:', name);
    try {
      const html = await request(`${PARTIALS}/${name}_panel.php`);
      content.innerHTML = html;
      console.log('[admin.js] injected:', name, 'len=', html?.length);
      attachHandlers(name);
    } catch (e) {
      console.error(e);
      content.innerHTML = `<p>Erro ao carregar: ${e.message || e}</p>`;
      showToast({ title: 'Erro', msg: e.message || 'Falha ao carregar conteúdo', type: 'err' });
    } finally {
      if (loading) loading.style.display = "none";
    }
  }

  // Anexa handlers específicos por aba
  function attachHandlers(name) {
    console.log('[admin.js] attachHandlers:', name);
    
    if (name === 'results') {
    const form = document.getElementById('resultsFilter');
    if (form) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const tid = form.tournament_id.value;
        try {
          const html = await request(`${PARTIALS}/results_panel.php?tournament_id=${encodeURIComponent(tid)}`);
          content.innerHTML = html;
          attachHandlers('results'); // rebind after re-render
        } catch (err) {
          console.error(err);
          if (window.showToast) showToast({ title: 'Erro', msg: 'Falha ao carregar resultados', type: 'err' });
        }
      });
    }
  }

    if (name === "tournaments") {
      const form = document.getElementById("formTournament");
      const table = document.getElementById("tournTable");

      form?.addEventListener("submit", async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        try {
          const j = await request(`${API}/tournaments_action.php`, { method: "POST", body: fd });
          if (j.ok) {
            showToast({ title: 'Sucesso', msg: 'Torneio guardado.', type: 'ok' });
            loadTab("tournaments");
          } else {
            showToast({ title: 'Erro', msg: j.erro || "Falha ao guardar torneio", type: 'err' });
          }
        } catch (err) {
          showToast({ title: 'Ligação', msg: err.message || "Erro de ligação", type: 'err' });
          console.error(err);
        }
      });

      table?.addEventListener("click", async (e) => {
        const btn = e.target.closest("button");
        if (!btn) return;

        if (btn.classList.contains("editT")) {
          const row = btn.closest("tr");
          form.action.value = "update";
          form.id.value = btn.dataset.id;
          form.name.value = row.children[1].textContent.trim();
          form.start_date.value = row.children[2].textContent.trim();
          form.end_date.value = row.children[3].textContent.trim();
          document.getElementById("tournCancel").style.display = "";
        } else if (btn.classList.contains("delT")) {
          if (!confirm("Apagar?")) return;
          const fd = new FormData();
          fd.append("action", "delete");
          fd.append("id", btn.dataset.id);
          fd.append("csrf", form?.querySelector('input[name="csrf"]')?.value || "");
          try {
            const j = await request(`${API}/tournaments_action.php`, { method: "POST", body: fd });
            if (j.ok) {
              showToast({ title: 'Sucesso', msg: 'Torneio apagado.', type: 'ok' });
              loadTab("tournaments");
            } else {
              showToast({ title: 'Erro', msg: j.erro || "Falha ao apagar torneio", type: 'err' });
            }
          } catch (err) {
            showToast({ title: 'Ligação', msg: err.message || "Erro de ligação", type: 'err' });
            console.error(err);
          }
        }
      });

      document.getElementById("tournCancel")?.addEventListener("click", () => {
        form.reset();
        form.action.value = "create";
        document.getElementById("tournCancel").style.display = "none";
      });
    }

    if (name === "phases") {
      const form = document.getElementById("formPhase");
      const table = document.getElementById("phasesTable");

      form?.addEventListener("submit", async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        try {
          const j = await request(`${API}/phases_actions.php`, { method: "POST", body: fd });
          if (j.ok) {
            showToast({ title: 'Sucesso', msg: 'Fase guardada.', type: 'ok' });
            loadTab("phases");
          } else {
            showToast({ title: 'Erro', msg: j.erro || "Falha ao guardar fase", type: 'err' });
          }
        } catch (err) {
          showToast({ title: 'Ligação', msg: err.message || "Erro de ligação", type: 'err' });
          console.error(err);
        }
      });

      table?.addEventListener("click", async (e) => {
        const btn = e.target.closest("button");
        if (!btn) return;

        if (btn.classList.contains("editPhase")) {
          const row = btn.closest("tr");
          form.action.value = "update";
          form.id.value = btn.dataset.id;
          form.tournament_id.value = row.dataset.tournamentId || "";
          form.name.value = row.children[2].textContent.trim();
          form.type.value = row.children[3].textContent.trim();
          document.getElementById("phaseCancel").style.display = "";
        } else if (btn.classList.contains("delPhase")) {
          if (!confirm("Apagar fase? (jogos ligados serão removidos)")) return;
          const fd = new FormData();
          fd.append("action", "delete");
          fd.append("id", btn.dataset.id);
          fd.append("csrf", form?.querySelector('input[name="csrf"]')?.value || "");
          try {
            const j = await request(`${API}/phases_actions.php`, { method: "POST", body: fd });
            if (j.ok) {
              showToast({ title: 'Sucesso', msg: 'Fase apagada.', type: 'ok' });
              loadTab("phases");
            } else {
              showToast({ title: 'Erro', msg: j.erro || "Falha ao apagar fase", type: 'err' });
            }
          } catch (err) {
            showToast({ title: 'Ligação', msg: err.message || "Erro de ligação", type: 'err' });
            console.error(err);
          }
        }
      });

      document.getElementById("phaseCancel")?.addEventListener("click", () => {
        form.reset();
        form.action.value = "create";
        document.getElementById("phaseCancel").style.display = "none";
      });
    }

    if (name === "players") {
      const form = document.getElementById("formPlayer");
      const table = document.getElementById("playersTable");

      form?.addEventListener("submit", async (e) => {
        e.preventDefault();
        const fd = new FormData(form); // inclui foto
        try {
          const j = await request(`${API}/players_actions.php`, { method: "POST", body: fd });
          if (j.ok) {
            showToast({ title: 'Sucesso', msg: 'Jogador guardado.', type: 'ok' });
            loadTab("players");
          } else {
            showToast({ title: 'Erro', msg: j.erro || "Falha ao guardar jogador", type: 'err' });
          }
        } catch (err) {
          showToast({ title: 'Ligação', msg: err.message || "Erro de ligação", type: 'err' });
          console.error(err);
        }
      });

      table?.addEventListener("click", async (e) => {
        const btn = e.target.closest("button");
        if (!btn) return;

        if (btn.classList.contains("editPlayer")) {
          const row = btn.closest("tr");
          form.action.value = "update";
          form.id.value = btn.dataset.id;
          form.team_id.value = row.dataset.teamId || "";
          form.name.value = row.children[2].textContent.trim();
          form.number.value = row.children[3].textContent.trim();
          form.position.value = row.children[4].textContent.trim();
          form.dob.value = row.children[5].textContent.trim();
          form.bi.value = row.children[6].textContent.trim();
          document.getElementById("playerCancel").style.display = "";
        } else if (btn.classList.contains("delPlayer")) {
          if (!confirm("Apagar jogador?")) return;
          const fd = new FormData();
          fd.append("action", "delete");
          fd.append("id", btn.dataset.id);
          fd.append("csrf", form?.querySelector('input[name="csrf"]')?.value || "");
          try {
            const j = await request(`${API}/players_actions.php`, { method: "POST", body: fd });
            if (j.ok) {
              showToast({ title: 'Sucesso', msg: 'Jogador apagado.', type: 'ok' });
              loadTab("players");
            } else {
              showToast({ title: 'Erro', msg: j.erro || "Falha ao apagar jogador", type: 'err' });
            }
          } catch (err) {
            showToast({ title: 'Ligação', msg: err.message || "Erro de ligação", type: 'err' });
            console.error(err);
          }
        }
      });

      document.getElementById("playerCancel")?.addEventListener("click", () => {
        form.reset();
        form.action.value = "create";
        document.getElementById("playerCancel").style.display = "none";
      });
    }

    if (name === 'staff') {
      const form = document.getElementById('formStaff');
      const table = document.getElementById('staffTable');

      form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form); // inclui foto
        try {
          const j = await request(`${API}/staff_actions.php`, { method: 'POST', body: fd });
          if (j.ok) {
            showToast({ title: 'Sucesso', msg: 'Staff guardado.', type: 'ok' });
            loadTab('staff');
          } else {
            showToast({ title: 'Erro', msg: j.erro || 'Falha ao guardar staff', type: 'err' });
          }
        } catch (err) {
          showToast({ title: 'Ligação', msg: err.message || 'Erro de ligação', type: 'err' });
          console.error(err);
        }
      });

      table?.addEventListener('click', async (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;

        if (btn.classList.contains('editStaff')) {
          const row = btn.closest('tr');
          form.action.value = 'update';
          form.id.value = btn.dataset.id;
          form.team_id.value = row.dataset.teamId || '';
          form.name.value = row.children[2].textContent.trim();
          form['function'].value = row.children[3].textContent.trim(); // bracket notation
          form.contact.value = row.children[4].textContent.trim();
          document.getElementById('staffCancel').style.display = '';
        } else if (btn.classList.contains('delStaff')) {
          if (!confirm('Apagar membro do staff?')) return;
          const fd = new FormData();
          fd.append('action', 'delete');
          fd.append('id', btn.dataset.id);
          fd.append('csrf', form?.querySelector('input[name="csrf"]')?.value || "");
          try {
            const j = await request(`${API}/staff_actions.php`, { method: 'POST', body: fd });
            if (j.ok) {
              showToast({ title: 'Sucesso', msg: 'Staff apagado.', type: 'ok' });
              loadTab('staff');
            } else {
              showToast({ title: 'Erro', msg: j.erro || 'Falha ao apagar staff', type: 'err' });
            }
          } catch (err) {
            showToast({ title: 'Ligação', msg: err.message || 'Erro de ligação', type: 'err' });
            console.error(err);
          }
        }
      });

      document.getElementById('staffCancel')?.addEventListener('click', () => {
        form.reset();
        form.action.value = 'create';
        document.getElementById('staffCancel').style.display = 'none';
      });
    }

    if (name === "teams") {
      const form = document.getElementById("formTeam");
      const table = document.getElementById("teamsTable");

      form?.addEventListener("submit", async (e) => {
        e.preventDefault();
        const fd = new FormData(form); // inclui arquivo logo
        try {
          const j = await request(`${API}/teams_actions.php`, { method: "POST", body: fd });
          if (j.ok) {
            showToast({ title: 'Sucesso', msg: 'Equipa guardada.', type: 'ok' });
            loadTab("teams");
          } else {
            showToast({ title: 'Erro', msg: j.erro || "Falha ao guardar equipa", type: 'err' });
          }
        } catch (err) {
          showToast({ title: 'Ligação', msg: err.message || "Erro de ligação", type: 'err' });
          console.error(err);
        }
      });

      table?.addEventListener("click", async (e) => {
        const btn = e.target.closest("button");
        if (!btn) return;

        if (btn.classList.contains("editTeam")) {
          const row = btn.closest("tr");
          form.action.value = "update";
          form.id.value = btn.dataset.id;
          form.name.value = row.children[2].textContent.trim();
          form.abbreviation.value = row.children[3].textContent.trim();
          form.city.value = row.children[4].textContent.trim();
          form.tournament_id.value = row.dataset.tournamentId || "";
          document.getElementById("teamCancel").style.display = "";
        } else if (btn.classList.contains("delTeam")) {
          if (!confirm("Apagar?")) return;
          const fd = new FormData();
          fd.append("action", "delete");
          fd.append("id", btn.dataset.id);
          fd.append("csrf", form?.querySelector('input[name="csrf"]')?.value || "");
          try {
            const j = await request(`${API}/teams_actions.php`, { method: "POST", body: fd });
            if (j.ok) {
              showToast({ title: 'Sucesso', msg: 'Equipa apagada.', type: 'ok' });
              loadTab("teams");
            } else {
              showToast({ title: 'Erro', msg: j.erro || "Falha ao apagar equipa", type: 'err' });
            }
          } catch (err) {
            showToast({ title: 'Ligação', msg: err.message || "Erro de ligação", type: 'err' });
            console.error(err);
          }
        }
      });

      document.getElementById("teamCancel")?.addEventListener("click", () => {
        form.reset();
        form.action.value = "create";
        document.getElementById("teamCancel").style.display = "none";
      });
    }

    if (name === 'matches') {
      const form = document.getElementById('formMatch');
      const table = document.getElementById('matchesTable');
      console.log('[matches] form?', !!form, 'table?', !!table);

      // Envio do formulário via fetch (sem reload)
      form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        console.log('[matches] submit');
        const fd = new FormData(form);
        try {
          const j = await request(`${API}/matches_actions.php`, { method: 'POST', body: fd });
          console.log('[matches] res', j);
          if (j.ok) {
            showToast({ title: 'Sucesso', msg: 'Jogo guardado.', type: 'ok' });
            loadTab('matches');
          } else {
            showToast({ title: 'Erro', msg: j.erro || 'Falha ao guardar jogo', type: 'err' });
          }
        } catch (err) {
          console.error('[matches] err', err);
          showToast({ title: 'Ligação', msg: err.message || 'Erro de ligação', type: 'err' });
        }
      });

      // Editar e Apagar
      table?.addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;
        if (!form) { console.error('formMatch não encontrado'); return; }

        if (btn.classList.contains('editMatch')) {
          const row = btn.closest('tr');
          if (!row) return;

          form.action.value = 'update';
          form.id.value = btn.dataset.id || row.dataset.id || '';

          // IDs vindos dos data-attributes da tr
          if (form.tournament_id) form.tournament_id.value = row.dataset.tournamentId || '';
          if (form.phase_id) form.phase_id.value = row.dataset.phaseId || '';
          if (form.team_home_id) form.team_home_id.value = row.dataset.homeId || '';
          if (form.team_away_id) form.team_away_id.value = row.dataset.awayId || '';

          // Data/Hora -> coluna 3 (YYYY-MM-DDTHH:MM)
          const dtCell = row.children[3]?.textContent?.trim() || '';
          if (form.match_date) form.match_date.value = dtCell.replace(' ', 'T').slice(0, 16);

          // Rodada -> coluna 7
          const roundCell = row.children[7]?.textContent?.trim() || '';
          if (form.round) form.round.value = roundCell;

          // Status -> coluna 8
          const statusCell = row.children[8]?.textContent?.trim() || 'agendado';
          if (form.status) form.status.value = statusCell;

          // Placar -> coluna 9: "X - Y"
          const scoreCell = row.children[9]?.textContent?.trim() || '';
          const m = scoreCell.match(/(\d+)\s*-\s*(\d+)/);
          if (m) {
            if (form.home_score) form.home_score.value = m[1];
            if (form.away_score) form.away_score.value = m[2];
          } else {
            if (form.home_score) form.home_score.value = '';
            if (form.away_score) form.away_score.value = '';
          }

          document.getElementById('matchCancel')?.style?.setProperty('display', '');
        }

        if (btn.classList.contains('delMatch')) {
          if (!confirm('Apagar jogo?')) return;
          const fd = new FormData();
          fd.append('action', 'delete');
          fd.append('id', btn.dataset.id || btn.closest('tr')?.dataset.id || '');
          const csrf = form?.querySelector('input[name="csrf"]')?.value
                    || document.querySelector('input[name="csrf"]')?.value
                    || '';
          fd.append('csrf', csrf);
          request(`${API}/matches_actions.php`, { method: 'POST', body: fd })
            .then(j => {
              if (j.ok) {
                showToast({ title: 'Sucesso', msg: 'Jogo apagado.', type: 'ok' });
                loadTab('matches');
              } else {
                showToast({ title: 'Erro', msg: j.erro || 'Falha ao apagar jogo', type: 'err' });
              }
            })
            .catch(err => {
              showToast({ title: 'Ligação', msg: err.message || 'Erro de ligação', type: 'err' });
              console.error(err);
            });
        }
      });

      // Cancelar edição
      document.getElementById('matchCancel')?.addEventListener('click', () => {
        form.reset();
        form.action.value = 'create';
        document.getElementById('matchCancel').style.display = 'none';
      });
    }
  }

  // Ligar abas com prevenção de navegação
const tabs = Array.from(document.querySelectorAll('nav [data-tab]'));
tabs.forEach((btn) => {
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    // Toggle visual active state
    tabs.forEach(b => b.classList.toggle('is-active', b === btn));
    loadTab(btn.dataset.tab);
  });
});

// Set initial active state to match the initial load
const firstTab = tabs[0];
if (firstTab) firstTab.classList.add('is-active');

  // Aba inicial
  loadTab("tournaments");
})();