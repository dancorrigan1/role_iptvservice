// public/monitor.mobile.js
(function () {
    const API = window.STATUS_API || 'api/status.php';
    const cards = document.getElementById('mCards');
    const countEl = document.getElementById('mCount');
    const searchEl = document.getElementById('mSearch');

    const btnAll      = document.getElementById('mAll');
    const btnWatching = document.getElementById('mWatching');
    const btnIdle     = document.getElementById('mIdle');
    const btnRefresh  = document.getElementById('mRefresh');

    let last = [];
    let mode = 'all';
    let query = '';

    function fmtDate(ts) {
      if (!ts) return '';
      try { return new Date(ts * 1000).toLocaleString(); }
      catch { return new Date(ts * 1000).toString(); }
    }

    function escape(s) {
      return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    function renderCard(s) {
      const watching = !!(s.program_title || s.channel_number || s.channel_name);
      const liveCls = watching ? 'm-live' : 'm-idle';
      const ipLabel = s.ip_label ? ` (${escape(s.ip_label)})` : '';
      const conn = (s.connections && Number(s.connections) > 1) ? ` • ${s.connections} conns` : '';
      const chanBits = [];
      if (s.channel_number) chanBits.push(escape(String(s.channel_number)));
      if (s.channel_name)   chanBits.push(`— <span class="m-sub">${escape(s.channel_name)}</span>`);
      const chanLine = chanBits.length ? `<div class="m-chan"><strong>Channel:</strong> ${chanBits.join(' ')}</div>` : '';

      const progLine = s.program_title ? `<div class="m-chan"><strong>Program:</strong> ${escape(s.program_title)}</div>` : '';
      const uaLine = s.user_agent ? `<div class="m-ua">${escape(s.user_agent)}</div>` : '';

      return `
        <article class="m-card ${liveCls}">
          <div class="m-row">
            <div class="m-titleline">${escape(s.proxy_name || '')}</div>
            <div class="m-badges">
              ${s.provider ? `<span class="m-badge">${escape(s.provider)}</span>` : ''}
              ${watching ? `<span class="m-badge">watching</span>` : `<span class="m-badge">idle</span>`}
            </div>
          </div>
          <div class="m-sub">${escape(s.username || '')} • ${escape(s.ip || '')}${ipLabel}${conn}</div>
          ${chanLine}
          ${progLine}
          <div class="m-sub" style="margin-top:6px"><strong>Since:</strong> ${fmtDate(s.since)}</div>
          ${uaLine}
        </article>
      `;
    }

    function applyFilterAndSearch(rows) {
      return rows.filter(s => {
        const watching = !!(s.program_title || s.channel_number || s.channel_name);
        if (mode === 'watching' && !watching) return false;
        if (mode === 'idle' && watching) return false;
        if (!query) return true;
        const hay = [
          s.proxy_name, s.username, s.ip, s.ip_label,
          s.provider, s.channel_number, s.channel_name, s.program_title, s.user_agent
        ].map(v => (v == null ? '' : String(v))).join(' ').toLowerCase();
        return hay.includes(query);
      });
    }

    function redraw() {
      const rows = applyFilterAndSearch(last);
      cards.innerHTML = rows.length ? rows.map(renderCard).join('') : `<div class="m-muted">No sessions</div>`;
      if (countEl) countEl.textContent = `${rows.length} session${rows.length===1?'':'s'}`;
    }

    async function load() {
      try {
        const r = await fetch(API, { cache: 'no-store' });
        const j = await r.json();
        if (!j || !j.ok) throw new Error((j && j.error) ? j.error : 'Unknown error');
        last = j.sessions || [];
        redraw();
      } catch (e) {
        console.error(e);
        cards.innerHTML = `<div class="m-muted">Failed to load: ${e.message}</div>`;
        if (countEl) countEl.textContent = '0 sessions';
      }
    }

    // controls
    btnAll      && btnAll.addEventListener('click', ()=>{ mode='all'; redraw(); });
    btnWatching && btnWatching.addEventListener('click', ()=>{ mode='watching'; redraw(); });
    btnIdle     && btnIdle.addEventListener('click', ()=>{ mode='idle'; redraw(); });
    btnRefresh  && btnRefresh.addEventListener('click', load);
    searchEl    && searchEl.addEventListener('input', (e)=>{ query = (e.target.value||'').toLowerCase().trim(); redraw(); });

    load();
    if (window.STATUS_AUTOREFRESH_SECONDS) {
      setInterval(load, Math.max(5, Number(window.STATUS_AUTOREFRESH_SECONDS)) * 1000);
    }
  })();
