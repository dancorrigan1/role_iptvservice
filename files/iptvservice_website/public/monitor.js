// public/monitor.js
(function () {
  const DEFAULT_API = 'api/status.php';
  const API = (window.STATUS_API || DEFAULT_API);

  const tbody = document.getElementById('liveSessionsBody');
  const countEl = document.getElementById('liveSessionsCount');

  function fmtDate(ts, tz) {
    if (!ts) return '';
    const d = new Date(ts * 1000);
    try { return d.toLocaleString(undefined, tz ? { timeZone: tz } : undefined); }
    catch { return d.toLocaleString(); }
  }

  function el(tag, cls, text) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined && text !== null) e.textContent = text;
    return e;
  }

  function renderRow(s, tz) {
    const tr = document.createElement('tr');

    tr.appendChild(el('td', '', s.proxy_name || ''));
    tr.appendChild(el('td', '', s.username || ''));

    const ipLabel = s.ip_label ? ` (${s.ip_label})` : '';
    const connBadge = (s.connections && Number(s.connections) > 1) ? ` [${s.connections}]` : '';
    const tdIp = el('td', '', (s.ip || '') + ipLabel + connBadge);
    if (s.ip_label) tdIp.title = `${s.ip} — ${s.ip_label}`;
    tr.appendChild(tdIp);

    tr.appendChild(el('td', '', s.provider || ''));

    const tdChan = el('td', '');
    if (s.channel_number) {
      tdChan.appendChild(document.createTextNode(String(s.channel_number)));
      if (s.channel_name) {
        tdChan.appendChild(document.createTextNode(' — '));
        tdChan.appendChild(el('span', 'muted', s.channel_name));
      }
    } else if (s.channel_name) {
      tdChan.appendChild(document.createTextNode(s.channel_name));
    }
    tr.appendChild(tdChan);

    tr.appendChild(el('td', '', s.program_title || ''));
    tr.appendChild(el('td', '', fmtDate(s.since, (window.STATUS_TZ || (s.meta && s.meta.tz)) )));
    tr.appendChild(el('td', '', s.user_agent || ''));

    return tr;
  }

  async function load() {
    try {
      const r = await fetch(API, { cache: 'no-store' });
      const j = await r.json();
      if (!j || !j.ok) throw new Error((j && j.error) ? j.error : 'Unknown error');

      const tz = (j.meta && j.meta.tz) || 'UTC';
      const sessions = j.sessions || [];

      tbody.innerHTML = '';
      sessions.forEach(s => tbody.appendChild(renderRow(s, tz)));

      if (countEl) countEl.textContent = `${sessions.length} sessions`;
    } catch (e) {
      console.error('Failed to load status:', e);
      tbody.innerHTML = `<tr><td colspan="8" class="muted">Failed to load status: ${e.message}</td></tr>`;
      if (countEl) countEl.textContent = '0 sessions';
    }
  }

  const btnRefresh  = document.getElementById('sessionsRefresh');
  const btnAll      = document.getElementById('sessionsShowAll');
  const btnWatching = document.getElementById('sessionsShowWatching');
  const btnIdle     = document.getElementById('sessionsShowIdle');

  btnRefresh && btnRefresh.addEventListener('click', load);

  function applyFilter(kind) {
    const rows = tbody.querySelectorAll('tr');
    rows.forEach((row) => {
      const prog = (row.children[5]?.textContent || '').trim();
      if (kind === 'watching')       row.style.display = prog ? '' : 'none';
      else if (kind === 'idle')      row.style.display = prog ? 'none' : '';
      else                           row.style.display = '';
    });
  }
  btnAll      && btnAll.addEventListener('click', () => applyFilter('all'));
  btnWatching && btnWatching.addEventListener('click', () => applyFilter('watching'));
  btnIdle     && btnIdle.addEventListener('click', () => applyFilter('idle'));

  load();
  if (window.STATUS_AUTOREFRESH_SECONDS) {
    setInterval(load, Math.max(5, Number(window.STATUS_AUTOREFRESH_SECONDS)) * 1000);
  }
})();
