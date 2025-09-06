// admin.js - simples loader + AJAX forms
(async function () {
  const content = document.getElementById("panelContent");
  const loading = document.getElementById("loading");

  const loadTab = async (name) => {
    loading.style.display = "";
    try {
      const res = await fetch(`/futsal-pj/admin/partials/${name}_panel.php`, {
        credentials: "same-origin",
      });
      if (!res.ok) throw new Error("Falha carregar");
      const html = await res.text();
      content.innerHTML = html;
      attachHandlers(name);
    } catch (e) {
      content.innerHTML = "<p>Erro ao carregar.</p>";
      console.error(e);
    } finally {
      loading.style.display = "none";
    }
  };

  const attachHandlers = (name) => {
    if (name === "tournaments") {
      const form = document.getElementById("formTournament");
      const table = document.getElementById("tournTable");
      form &&
        form.addEventListener("submit", async (e) => {
          e.preventDefault();
          const fd = new FormData(form);
          try {
            const r = await fetch("/futsal-pj/new-api/tournaments_action.php", {
              method: "POST",
              body: fd,
              credentials: "same-origin",
            });
            const j = await r.json();
            if (j.ok) loadTab("tournaments");
            else alert(j.erro || "Erro");
          } catch (err) {
            alert("Erro de ligação");
            console.error(err);
          }
        });
      table &&
        table.addEventListener("click", async (e) => {
          if (e.target.matches(".editT")) {
            const id = e.target.dataset.id;
            const row = e.target.closest("tr");
            form.action.value = "update";
            form.id.value = id;
            form.name.value = row.children[1].textContent.trim();
            form.start_date.value = row.children[2].textContent.trim();
            form.end_date.value = row.children[3].textContent.trim();
            document.getElementById("tournCancel").style.display = "";
          } else if (e.target.matches(".delT")) {
            if (!confirm("Apagar?")) return;
            const fd = new FormData();
            fd.append("action", "delete");
            fd.append("id", e.target.dataset.id);
            fd.append(
              "csrf",
              document.querySelector('input[name="csrf"]').value
            );
            const r = await fetch("/futsal-pj/new-api/tournaments_action.php", {
              method: "POST",
              body: fd,
              credentials: "same-origin",
            });
            const j = await r.json();
            if (j.ok) loadTab("tournaments");
            else alert(j.erro || "Erro");
          }
        });
      const cancel = document.getElementById("tournCancel");
      cancel &&
        cancel.addEventListener("click", () => {
          document.getElementById("formTournament").reset();
          document.getElementById("formTournament").action.value = "create";
          cancel.style.display = "none";
        });
    }

    if (name === "teams") {
      const form = document.getElementById("formTeam");
      const table = document.getElementById("teamsTable");
      form &&
        form.addEventListener("submit", async (e) => {
          e.preventDefault();
          const fd = new FormData(form);
          try {
            const r = await fetch("/futsal-pj/new-api/teams_action.php", {
              method: "POST",
              body: fd,
              credentials: "same-origin",
            });
            const j = await r.json();
            if (j.ok) loadTab("teams");
            else alert(j.erro || "Erro");
          } catch (err) {
            alert("Erro de ligação");
            console.error(err);
          }
        });
      table &&
        table.addEventListener("click", async (e) => {
          if (e.target.matches(".editTeam")) {
            const id = e.target.dataset.id;
            const row = e.target.closest("tr");
            form.action.value = "update";
            form.id.value = id;
            form.name.value = row.children[2].textContent.trim();
            form.abbreviation.value = row.children[3].textContent.trim();
            form.city.value = row.children[4].textContent.trim();
            form.tournament_id.value = row.dataset.tournamentId || "";
            document.getElementById("teamCancel").style.display = "";
          } else if (e.target.matches(".delTeam")) {
            if (!confirm("Apagar?")) return;
            const fd = new FormData();
            fd.append("action", "delete");
            fd.append("id", e.target.dataset.id);
            fd.append(
              "csrf",
              document.querySelector('input[name="csrf"]').value
            );
            const r = await fetch("/futsal-pj/new-api/teams_action.php", {
              method: "POST",
              body: fd,
              credentials: "same-origin",
            });
            const j = await r.json();
            if (j.ok) loadTab("teams");
            else alert(j.erro || "Erro");
          }
        });
      const cancel = document.getElementById("teamCancel");
      cancel &&
        cancel.addEventListener("click", () => {
          document.getElementById("formTeam").reset();
          document.getElementById("formTeam").action.value = "create";
          cancel.style.display = "none";
        });
    }
  };

  // bind nav
  document.querySelectorAll("nav [data-tab]").forEach((btn) => {
    btn.addEventListener("click", () => loadTab(btn.dataset.tab));
  });

  // load default
  loadTab("tournaments");
})();
