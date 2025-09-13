
  // URL helpers
  function getParam(name) {
    const url = new URL(window.location.href);
    return url.searchParams.get(name);
  }
  function setParams(params) {
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([k, v]) => {
      if (v === null || v === undefined || v === '') url.searchParams.delete(k);
      else url.searchParams.set(k, v);
    });
    window.location.href = url.toString();
  }

  // Initialize selects based on URL ?group=
  (function initSelects() {
    const group = getParam('group') || 'tournament'; // 'tournament' | 'A' | 'B'
    const s1 = document.getElementById('selectStandings');
    const s2 = document.getElementById('selectResults');
    if (s1) s1.value = group;
    if (s2) s2.value = group;

    if (s1) s1.addEventListener('change', e => {
      setParams({ group: e.target.value, view: 'standings' });
    });
    if (s2) s2.addEventListener('change', e => {
      setParams({ group: e.target.value, view: 'results' });
    });
  })();

  // Escape helper
  function escapeHtml(s){ return (s==null?'':String(s)).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  // Load Next Games (status='agendado'), up to 4, optionally filtered by group A/B
  async function loadNextGames() {
    const container = document.getElementById('nextGames');
    if (!container) return;
    const group = getParam('group') || 'tournament';

    try {
      const res = await fetch(`next_games.php?group=${encodeURIComponent(group)}`);
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const games = await res.json();
      container.innerHTML = '';

      if (!Array.isArray(games) || games.length === 0) {
        container.innerHTML = `
          <div class="team-score" style="padding: .4rem 0;">
            <div class="team-group">
              <div class="team" style="opacity:.8;">Sem jogos agendados.</div>
              <span>—</span>
            </div>
          </div>`;
        return;
      }

      games.slice(0,4).forEach(g => {
        const item = document.createElement('div');
        item.className = 'team-score';
        item.innerHTML = `
          <div class="team-group">
            <div class="team">${escapeHtml(g.home_name)}</div>
            <span>vs</span>
          </div>
          <div class="team-group">
            <div class="team" style="opacity:.9; font-size:.9rem;">
              ${escapeHtml(g.date)} ${escapeHtml(g.time)} • ${escapeHtml(g.tournament_name)} • ${escapeHtml(g.phase_name)}
            </div>
            <span>${escapeHtml(g.away_name)}</span>
          </div>
        `;
        container.appendChild(item);
      });
    } catch (e) {
      console.error('Failed to load next games', e);
    }
  }

  loadNextGames();
