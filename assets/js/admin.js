// assets/js/admin.js
// Loader de partials + handlers CRUD para torneios, fases, equipas, jogadores, staff e jogos.
// Requer no ad_panel.php: <body data-base="" data-api="" data-partials=""> e um container #panelContent.

/* Toast messages (robusto e sucinto) */
(function () {
  function ensureToastRoot() {
    let root = document.querySelector(".toasts");
    if (!root) {
      root = document.createElement("div");
      root.className = "toasts";
      root.setAttribute("aria-live", "polite");
      root.setAttribute("aria-atomic", "true");
      (document.body || document.documentElement).appendChild(root);
    }
    return root;
  }
  function makeToastElement({ title, msg, type }) {
    const el = document.createElement("div");
    el.className = `toast toast--${type}`;
    el.setAttribute("role", type === "err" ? "alert" : "status");
    el.innerHTML = `<div class="title">${title}</div><div class="msg">${msg}</div>`;
    el.style.opacity = "0";
    el.style.transition = "opacity .22s ease, transform .22s ease";
    el.style.transform = "translateY(6px)";
    return el;
  }
  function showToast({
    title = "Info",
    msg = "",
    type = "info",
    timeout = 3500,
  } = {}) {
    try {
      const root = ensureToastRoot();
      const el = makeToastElement({ title, msg, type });
      root.appendChild(el);
      requestAnimationFrame(() => {
        el.style.opacity = "1";
        el.style.transform = "translateY(0)";
      });
      const close = () => {
        el.style.opacity = "0";
        el.style.transform = "translateY(6px)";
        setTimeout(() => {
          try {
            el.remove();
          } catch {}
        }, 260);
      };
      const tId = setTimeout(close, timeout);
      el.addEventListener("click", () => {
        clearTimeout(tId);
        close();
      });
      el.tabIndex = -1;
      el.focus({ preventScroll: true });
    } catch (err) {
      console.error("showToast failed", err);
    }
  }
  window.showToast = showToast;
})();

(function () {
  "use strict";
  console.log("[admin.js] loaded");

  // Base + endpoints
  const content = document.getElementById("panelContent");
  const BASE = document.body.dataset.base || "";
  const API = document.body.dataset.api || BASE + "/new-api";
  const PARTIALS = document.body.dataset.partials || BASE + "/admin/partials";

  if (!content) {
    console.warn(
      "admin.js: #panelContent não encontrado. Verifique o ad_panel.php"
    );
  }

  // Utils
  async function request(url, opts = {}) {
    const res = await fetch(url, {
      credentials: "same-origin",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        ...(opts.headers || {}),
      },
      cache: "no-store",
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
  function getCsrf(scopeEl) {
    return (
      scopeEl?.querySelector('input[name="csrf_token"]')?.value ||
      scopeEl?.querySelector('input[name="csrf"]')?.value ||
      document.querySelector('input[name="csrf_token"]')?.value ||
      document.querySelector('input[name="csrf"]')?.value ||
      ""
    );
  }
  function appendCsrf(fd, form) {
    const token = getCsrf(form);
    if (token) {
      fd.set("csrf", token);
      fd.set("csrf_token", token);
    }
  }
  async function loadTab(name) {
    const loading = document.getElementById("loading");
    if (loading) loading.style.display = "";
    try {
      const html = await request(`${PARTIALS}/${name}_panel.php`);
      if (!html || !html.trim())
        throw new Error("Servidor retornou conteúdo vazio.");
      content.innerHTML = html;
      attachHandlers(name);
    } catch (e) {
      console.error(e);
      content.innerHTML = `<p>Erro ao carregar: ${e.message || e}</p>`;
      showToast({
        title: "Erro",
        msg: e.message || "Falha ao carregar conteúdo",
        type: "err",
      });
    } finally {
      if (loading) loading.style.display = "none";
    }
  }
  async function postForm(url, form) {
    const fd = new FormData(form);
    appendCsrf(fd, form);
    return request(url, { method: "POST", body: fd });
  }
  async function doDelete(url, id, form, confirmMsg = "Apagar?") {
    if (!confirm(confirmMsg)) return null;
    const fd = new FormData();
    fd.append("action", "delete");
    fd.append("id", String(id));
    appendCsrf(fd, form);
    return request(url, { method: "POST", body: fd });
  }

  // Handlers por aba usando switch-case
  function attachHandlers(name) {
    console.log("[admin.js] attachHandlers:", name);
    switch (name) {
      case "results": {
        const form = document.getElementById("resultsFilter");
        form?.addEventListener("submit", async (e) => {
          e.preventDefault();
          const tid = form.tournament_id.value;
          try {
            const html = await request(
              `${PARTIALS}/results_panel.php?tournament_id=${encodeURIComponent(
                tid
              )}`
            );
            content.innerHTML = html;
            attachHandlers("results");
          } catch (err) {
            console.error(err);
            showToast({
              title: "Erro",
              msg: "Falha ao carregar resultados",
              type: "err",
            });
          }
        });
        break;
      }

      case "tournaments": {
        const form = document.getElementById("formTournament");
        const table = document.getElementById("tournTable");

        form?.addEventListener("submit", async (e) => {
          e.preventDefault();
          try {
            const j = await postForm(`${API}/tournaments_action.php`, form);
            if (j.ok) {
              showToast({
                title: "Sucesso",
                msg: "Torneio guardado.",
                type: "ok",
              });
              loadTab("tournaments");
            } else {
              showToast({
                title: "Erro",
                msg: j.erro || "Falha ao guardar torneio",
                type: "err",
              });
            }
          } catch (err) {
            showToast({
              title: "Ligação",
              msg: err.message || "Erro de ligação",
              type: "err",
            });
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
            try {
              const j = await doDelete(
                `${API}/tournaments_action.php`,
                btn.dataset.id,
                form,
                "Apagar?"
              );
              if (j?.ok) {
                showToast({
                  title: "Sucesso",
                  msg: "Torneio apagado.",
                  type: "ok",
                });
                loadTab("tournaments");
              } else if (j) {
                showToast({
                  title: "Erro",
                  msg: j.erro || "Falha ao apagar torneio",
                  type: "err",
                });
              }
            } catch (err) {
              showToast({
                title: "Ligação",
                msg: err.message || "Erro de ligação",
                type: "err",
              });
              console.error(err);
            }
          }
        });

        document
          .getElementById("tournCancel")
          ?.addEventListener("click", () => {
            form.reset();
            form.action.value = "create";
            document.getElementById("tournCancel").style.display = "none";
          });
        break;
      }

      case "phases": {
        const form = document.getElementById("formPhase");
        const table = document.getElementById("phasesTable");

        form?.addEventListener("submit", async (e) => {
          e.preventDefault();
          try {
            const j = await postForm(`${API}/phases_actions.php`, form);
            if (j.ok) {
              showToast({
                title: "Sucesso",
                msg: "Fase guardada.",
                type: "ok",
              });
              loadTab("phases");
            } else {
              showToast({
                title: "Erro",
                msg: j.erro || "Falha ao guardar fase",
                type: "err",
              });
            }
          } catch (err) {
            showToast({
              title: "Ligação",
              msg: err.message || "Erro de ligação",
              type: "err",
            });
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
            try {
              const j = await doDelete(
                `${API}/phases_actions.php`,
                btn.dataset.id,
                form,
                "Apagar fase? (jogos ligados serão removidos)"
              );
              if (j?.ok) {
                showToast({
                  title: "Sucesso",
                  msg: "Fase apagada.",
                  type: "ok",
                });
                loadTab("phases");
              } else if (j) {
                showToast({
                  title: "Erro",
                  msg: j.erro || "Falha ao apagar fase",
                  type: "err",
                });
              }
            } catch (err) {
              showToast({
                title: "Ligação",
                msg: err.message || "Erro de ligação",
                type: "err",
              });
              console.error(err);
            }
          }
        });

        document
          .getElementById("phaseCancel")
          ?.addEventListener("click", () => {
            form.reset();
            form.action.value = "create";
            document.getElementById("phaseCancel").style.display = "none";
          });
        break;
      }

      case "players": {
        const form = document.getElementById("formPlayer");
        const table = document.getElementById("playersTable");

        form?.addEventListener("submit", async (e) => {
          e.preventDefault();
          try {
            const j = await postForm(`${API}/players_actions.php`, form);
            if (j.ok) {
              showToast({
                title: "Sucesso",
                msg: "Jogador guardado.",
                type: "ok",
              });
              loadTab("players");
            } else {
              showToast({
                title: "Erro",
                msg: j.erro || "Falha ao guardar jogador",
                type: "err",
              });
            }
          } catch (err) {
            showToast({
              title: "Ligação",
              msg: err.message || "Erro de ligação",
              type: "err",
            });
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
            try {
              const j = await doDelete(
                `${API}/players_actions.php`,
                btn.dataset.id,
                form,
                "Apagar jogador?"
              );
              if (j?.ok) {
                showToast({
                  title: "Sucesso",
                  msg: "Jogador apagado.",
                  type: "ok",
                });
                loadTab("players");
              } else if (j) {
                showToast({
                  title: "Erro",
                  msg: j.erro || "Falha ao apagar jogador",
                  type: "err",
                });
              }
            } catch (err) {
              showToast({
                title: "Ligação",
                msg: err.message || "Erro de ligação",
                type: "err",
              });
              console.error(err);
            }
          }
        });

        document
          .getElementById("playerCancel")
          ?.addEventListener("click", () => {
            form.reset();
            form.action.value = "create";
            document.getElementById("playerCancel").style.display = "none";
          });
        break;
      }

      case "staff": {
        const form = document.getElementById("formStaff");
        const table = document.getElementById("staffTable");

        form?.addEventListener("submit", async (e) => {
          e.preventDefault();
          try {
            const j = await postForm(`${API}/staff_actions.php`, form);
            if (j.ok) {
              showToast({
                title: "Sucesso",
                msg: "Staff guardado.",
                type: "ok",
              });
              loadTab("staff");
            } else {
              showToast({
                title: "Erro",
                msg: j.erro || "Falha ao guardar staff",
                type: "err",
              });
            }
          } catch (err) {
            showToast({
              title: "Ligação",
              msg: err.message || "Erro de ligação",
              type: "err",
            });
            console.error(err);
          }
        });

        table?.addEventListener("click", async (e) => {
          const btn = e.target.closest("button");
          if (!btn) return;

          if (btn.classList.contains("editStaff")) {
            const row = btn.closest("tr");
            form.action.value = "update";
            form.id.value = btn.dataset.id;
            form.team_id.value = row.dataset.teamId || "";
            form.name.value = row.children[2].textContent.trim();
            form["function"].value = row.children[3].textContent.trim();
            form.contact.value = row.children[4].textContent.trim();
            document.getElementById("staffCancel").style.display = "";
          } else if (btn.classList.contains("delStaff")) {
            try {
              const j = await doDelete(
                `${API}/staff_actions.php`,
                btn.dataset.id,
                form,
                "Apagar membro do staff?"
              );
              if (j?.ok) {
                showToast({
                  title: "Sucesso",
                  msg: "Staff apagado.",
                  type: "ok",
                });
                loadTab("staff");
              } else if (j) {
                showToast({
                  title: "Erro",
                  msg: j.erro || "Falha ao apagar staff",
                  type: "err",
                });
              }
            } catch (err) {
              showToast({
                title: "Ligação",
                msg: err.message || "Erro de ligação",
                type: "err",
              });
              console.error(err);
            }
          }
        });

        document
          .getElementById("staffCancel")
          ?.addEventListener("click", () => {
            form.reset();
            form.action.value = "create";
            document.getElementById("staffCancel").style.display = "none";
          });
        break;
      }

      case "teams": {
        const form = document.getElementById("formTeam");
        const table = document.getElementById("teamsTable");

        form?.addEventListener("submit", async (e) => {
          e.preventDefault();
          try {
            const j = await postForm(`${API}/teams_actions.php`, form);
            if (j.ok) {
              showToast({
                title: "Sucesso",
                msg: "Equipa guardada.",
                type: "ok",
              });
              loadTab("teams");
            } else {
              showToast({
                title: "Erro",
                msg: j.erro || "Falha ao guardar equipa",
                type: "err",
              });
            }
          } catch (err) {
            showToast({
              title: "Ligação",
              msg: err.message || "Erro de ligação",
              type: "err",
            });
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
            // Colunas: 0 ID | 1 Logo | 2 Nome | 3 Abrev | 4 Grupo | 5 Cidade | 6 Torneio | 7 Ações
            form.name.value = row.children[2].textContent.trim();
            form.abbreviation.value = row.children[3].textContent.trim();
            if (form.group_label)
              form.group_label.value =
                row.children[4].textContent.trim() || "A";
            form.city.value = row.children[5].textContent.trim();
            form.tournament_id.value = row.dataset.tournamentId || "";
            document.getElementById("teamCancel").style.display = "";
          } else if (btn.classList.contains("delTeam")) {
            try {
              const j = await doDelete(
                `${API}/teams_actions.php`,
                btn.dataset.id,
                form,
                "Apagar?"
              );
              if (j?.ok) {
                showToast({
                  title: "Sucesso",
                  msg: "Equipa apagada.",
                  type: "ok",
                });
                loadTab("teams");
              } else if (j) {
                showToast({
                  title: "Erro",
                  msg: j.erro || "Falha ao apagar equipa",
                  type: "err",
                });
              }
            } catch (err) {
              showToast({
                title: "Ligação",
                msg: err.message || "Erro de ligação",
                type: "err",
              });
              console.error(err);
            }
          }
        });

        document.getElementById("teamCancel")?.addEventListener("click", () => {
          form.reset();
          form.action.value = "create";
          document.getElementById("teamCancel").style.display = "none";
        });
        break;
      }

      case "matches": {
        const form = document.getElementById("formMatch");
        const table = document.getElementById("matchesTable");
        const tournSel = document.getElementById("matchTournament");
        const phaseSel = document.getElementById("matchPhase");
        const homeSel = document.getElementById("homeTeam");
        const awaySel = document.getElementById("awayTeam");
        const homeStats = document.getElementById("homeStats");
        const awayStats = document.getElementById("awayStats");
        const btnAddHomeStat = document.getElementById("btnAddHomeStat");
        const btnAddAwayStat = document.getElementById("btnAddAwayStat");

        function filterByTournament() {
          const t = tournSel?.value || "";
          [phaseSel, homeSel, awaySel].forEach((sel) => {
            if (!sel) return;
            [...sel.options].forEach((opt) => {
              if (!opt.value) return;
              opt.hidden = t && opt.dataset.tourn !== t;
            });
            if (sel.selectedOptions[0]?.hidden) sel.value = "";
          });
          refreshPlayerSelects();
        }
        tournSel?.addEventListener("change", filterByTournament);
        homeSel?.addEventListener("change", refreshPlayerSelects);
        awaySel?.addEventListener("change", refreshPlayerSelects);

        async function fetchPlayersByTeam(teamId) {
          if (!teamId) return [];
          try {
            const fd = new FormData();
            fd.append("action", "list_by_team");
            fd.append("team_id", String(teamId));
            appendCsrf(fd, form);
            const j = await request(`${API}/players_actions.php`, {
              method: "POST",
              body: fd,
            });
            return j && j.ok && Array.isArray(j.players) ? j.players : [];
          } catch (err) {
            console.warn("Falha ao carregar jogadores da equipa", teamId, err);
            showToast({
              title: "Aviso",
              msg: "Não foi possível carregar jogadores para seleção.",
              type: "warn",
            });
            return [];
          }
        }
        async function populatePlayerSelect(selectEl, side) {
          const teamSel = side === "home" ? homeSel : awaySel;
          const tid = parseInt(teamSel?.value || "0", 10);
          selectEl.innerHTML = '<option value="">— jogador —</option>';
          if (!tid) return;
          const players = await fetchPlayersByTeam(tid);
          for (const p of players) {
            const opt = document.createElement("option");
            opt.value = p.id;
            opt.textContent = p.name;
            selectEl.appendChild(opt);
          }
        }
        function makeStatRow(side) {
          const wrap = document.createElement("div");
          wrap.className = "stat-row";
          wrap.style.display = "grid";
          wrap.style.gridTemplateColumns = "1fr repeat(3, 100px) auto";
          wrap.style.gap = "6px";
          wrap.style.alignItems = "center";
          wrap.innerHTML = `
            <select class="select stat-player"></select>
            <input class="input stat-goals" type="number" min="0" step="1" value="0" title="Golos">
            <input class="input stat-yellow" type="number" min="0" step="1" value="0" title="Amarelos">
            <input class="input stat-red" type="number" min="0" step="1" value="0" title="Vermelhos">
            <button type="button" class="btn btn--danger stat-del" title="Remover linha">&times;</button>
          `;
          const sel = wrap.querySelector(".stat-player");
          populatePlayerSelect(sel, side);
          wrap
            .querySelector(".stat-del")
            .addEventListener("click", () => wrap.remove());
          return wrap;
        }
        function refreshPlayerSelects() {
          [...(homeStats?.querySelectorAll(".stat-row") || [])].forEach(
            (row) => {
              populatePlayerSelect(row.querySelector(".stat-player"), "home");
            }
          );
          [...(awayStats?.querySelectorAll(".stat-row") || [])].forEach(
            (row) => {
              populatePlayerSelect(row.querySelector(".stat-player"), "away");
            }
          );
        }
        btnAddHomeStat?.addEventListener("click", () => {
          homeStats?.appendChild(makeStatRow("home"));
        });
        btnAddAwayStat?.addEventListener("click", () => {
          awayStats?.appendChild(makeStatRow("away"));
        });

        // Submit: agrega stats e monta match_date preservando o hidden se não editar Data/Hora
        form?.addEventListener("submit", async (e) => {
          e.preventDefault();

          const agg = new Map();
          function collect(container) {
            if (!container) return;
            for (const row of container.querySelectorAll(".stat-row")) {
              const pid = parseInt(
                row.querySelector(".stat-player")?.value || "0",
                10
              );
              if (!pid) continue;
              const g = Math.max(
                0,
                parseInt(row.querySelector(".stat-goals")?.value || "0", 10)
              );
              const y = Math.max(
                0,
                parseInt(row.querySelector(".stat-yellow")?.value || "0", 10)
              );
              const r = Math.max(
                0,
                parseInt(row.querySelector(".stat-red")?.value || "0", 10)
              );
              if (!agg.has(pid))
                agg.set(pid, {
                  player_id: pid,
                  goals: 0,
                  yellow_cards: 0,
                  red_cards: 0,
                });
              const o = agg.get(pid);
              o.goals += g;
              o.yellow_cards += y;
              o.red_cards += r;
            }
          }
          collect(homeStats);
          collect(awayStats);
          const stats = Array.from(agg.values()).filter(
            (s) => s.goals || s.yellow_cards || s.red_cards
          );

          const fd = new FormData(form);
          fd.set("stats_json", JSON.stringify(stats));

          const d = form.match_date_date?.value?.trim() || "";
          const t = form.match_date_time?.value?.trim() || "";
          let md = d && t ? `${d}T${t}` : form.match_date?.value?.trim() || "";
          if (!md) {
            showToast({
              title: "Validação",
              msg: "Informe data e hora do jogo.",
              type: "warn",
            });
            form.match_date_date?.focus?.();
            return;
          }
          fd.set("match_date", md);
          appendCsrf(fd, form);

          try {
            const j = await request(`${API}/matches_actions.php`, {
              method: "POST",
              body: fd,
            });
            if (j.ok) {
              showToast({
                title: "Sucesso",
                msg: "Jogo guardado.",
                type: "ok",
              });
              loadTab("matches");
            } else {
              showToast({
                title: "Erro",
                msg: j.erro || "Falha ao guardar jogo",
                type: "err",
              });
            }
          } catch (err) {
            console.error("[matches] err", err);
            showToast({
              title: "Ligação",
              msg: err.message || "Erro de ligação",
              type: "err",
            });
          }
        });

        // Editar / Apagar
        table?.addEventListener("click", async (e) => {
          const btn = e.target.closest("button");
          if (!btn) return;
          if (!form) {
            console.error("formMatch não encontrado");
            return;
          }

          if (btn.classList.contains("editMatch")) {
            const row = btn.closest("tr");
            if (!row) return;

            form.action.value = "update";
            form.id.value = btn.dataset.id || row.dataset.id || "";

            if (form.tournament_id)
              form.tournament_id.value = row.dataset.tournamentId || "";
            filterByTournament();
            if (form.phase_id) form.phase_id.value = row.dataset.phaseId || "";
            if (form.team_home_id)
              form.team_home_id.value = row.dataset.homeId || "";
            if (form.team_away_id)
              form.team_away_id.value = row.dataset.awayId || "";

            // Col.: 0 Torneio | 1 Fase | 2 Data/Hora | 3 Mandante | 4 VS | 5 Visitante | 6 Rodada | 7 Status | 8 Placar
            const dtCell = row.children[2]?.textContent?.trim() || "";
            if (form.match_date) form.match_date.value = dtCell; // preserva valor atual

            if (dtCell && form.match_date_date && form.match_date_time) {
              const [datePart, timePart] = dtCell.split("T");
              if (datePart && timePart) {
                form.match_date_date.value = datePart;
                form.match_date_time.value = timePart;
              }
            } else if (form.match_date) {
              form.match_date.value = dtCell.replace(" ", "T").slice(0, 16);
            }

            if (form.round)
              form.round.value = row.children[6]?.textContent?.trim() || "";
            if (form.status)
              form.status.value =
                row.children[7]?.textContent?.trim() || "agendado";

            const m = (row.children[8]?.textContent?.trim() || "").match(
              /(\d+)\s*-\s*(\d+)/
            );
            if (m) {
              if (form.home_score) form.home_score.value = m[1];
              if (form.away_score) form.away_score.value = m[2];
            } else {
              if (form.home_score) form.home_score.value = "0";
              if (form.away_score) form.away_score.value = "0";
            }

            document
              .getElementById("matchCancel")
              ?.style?.setProperty("display", "");
            refreshPlayerSelects();
          }

          if (btn.classList.contains("delMatch")) {
            try {
              const j = await doDelete(
                `${API}/matches_actions.php`,
                btn.dataset.id || btn.closest("tr")?.dataset.id || "",
                form,
                "Apagar jogo?"
              );
              if (j?.ok) {
                showToast({
                  title: "Sucesso",
                  msg: "Jogo apagado.",
                  type: "ok",
                });
                loadTab("matches");
              } else if (j) {
                showToast({
                  title: "Erro",
                  msg: j.erro || "Falha ao apagar jogo",
                  type: "err",
                });
              }
            } catch (err) {
              console.error("[matches] delete err", err);
              showToast({
                title: "Ligação",
                msg: err.message || "Erro de ligação",
                type: "err",
              });
            }
          }
        });

        document
          .getElementById("matchCancel")
          ?.addEventListener("click", () => {
            form.reset();
            form.action.value = "create";
            if (form.match_date) form.match_date.value = "";
            document.getElementById("matchCancel").style.display = "none";
            if (homeStats) homeStats.innerHTML = "";
            if (awayStats) awayStats.innerHTML = "";
          });

        filterByTournament();
        break;
      }

      default:
        break;
    }
  }

  // Tabs
  const tabs = Array.from(document.querySelectorAll("nav [data-tab]"));
  tabs.forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      tabs.forEach((b) => b.classList.toggle("is-active", b === btn));
      loadTab(btn.dataset.tab);
    });
  });
  const firstTab = tabs[0];
  if (firstTab) firstTab.classList.add("is-active");

  // Aba inicial
  loadTab("tournaments");
})();
