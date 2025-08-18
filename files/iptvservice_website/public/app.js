// public/app.js
// -----------------------------------------------------------------------------
// Provider editor UI + Live Sessions dashboard
// - Safe if some elements/sections are missing (guards everywhere)
// - Keeps your "Live On/Off/All" filter for proxy users (entitlement flag)
// - Adds Live Sessions polling from api/status.php with its own filters
// -----------------------------------------------------------------------------

"use strict";

/* ----------------------------- DOM References ------------------------------ */
const container = document.getElementById('providersContainer'); // providers grid
const addProviderBtn = document.getElementById('addProviderBtn');
const refreshRepoBtn = document.getElementById('refreshRepoBtn');
const expandAllBtn = document.getElementById('expandAll');
const collapseAllBtn = document.getElementById('collapseAll');
const filterInput = document.getElementById('filterInput');

const liveAllBtn = document.getElementById('liveAllBtn');
const liveOnBtn  = document.getElementById('liveOnBtn');
const liveOffBtn = document.getElementById('liveOffBtn');

const provCountEl = document.getElementById('provCount');
const userCountEl = document.getElementById('userCount');
const credCountEl = document.getElementById('credCount');
const dupWarnEl   = document.getElementById('dupWarn');
const summaryBadgeEl = document.getElementById('summaryBadge');

// Live Sessions card (optional if present)
const liveTable = document.getElementById('liveTable');
const liveSub   = document.getElementById('liveSub');
const refreshNowBtn  = document.getElementById('refreshNow');
const liveShowAllBtn = document.getElementById('liveShowAll');
const liveShowOnBtn  = document.getElementById('liveShowOn');
const liveShowOffBtn = document.getElementById('liveShowOff');

/* ------------------------------- Helpers ----------------------------------- */
function safeQueryAll(root, sel) {
  return root ? Array.from(root.querySelectorAll(sel)) : [];
}
function setText(el, text) { if (el) el.textContent = text; }

/* ------------------------ Provider Editor Functions ------------------------ */
function updateProviderIndices() {
  if (!container) return;
  safeQueryAll(container, '.provider-fieldset').forEach((provider, index) => {
    provider.setAttribute('data-index', String(index));
    const num = provider.querySelector('.provider-number');
    if (num) num.textContent = String(index + 1);

    // Update only the first providers[\d+] in each name
    safeQueryAll(provider, 'input, select, textarea').forEach(input => {
      if (input.name && /providers\[\d+\]/.test(input.name)) {
        input.name = input.name.replace(/providers\[\d+\]/, 'providers[' + index + ']');
      }
    });

    refreshCounts(provider);
  });
  refreshSummary();
}

function refreshCounts(card) {
  if (!card) return;
  const users = safeQueryAll(card, '.users-container .user-row').length;
  const creds = safeQueryAll(card, '.credentials-container .credential-row').length;

  const sub = card.querySelector('.card-sub');
  if (sub) sub.textContent = 'Users ' + users + ' • Creds ' + creds;

  const uc = card.querySelector('.user-count'); if (uc) uc.textContent = String(users);
  const cc = card.querySelector('.cred-count'); if (cc) cc.textContent = String(creds);
}

function refreshSummary() {
  if (!container) return;
  const cards = safeQueryAll(container, '.provider-fieldset');
  const pCount = cards.length;
  const uCount = cards.reduce((n, c) => n + safeQueryAll(c, '.user-row').length, 0);
  const cCount = cards.reduce((n, c) => n + safeQueryAll(c, '.credential-row').length, 0);

  setText(provCountEl, pCount + ' providers');
  setText(userCountEl, uCount + ' users');
  setText(credCountEl, cCount + ' creds');
  setText(summaryBadgeEl, pCount + ' providers • ' + uCount + ' users');

  dedupeCheck();
}

function dedupeCheck() {
  if (!container || !dupWarnEl) return;
  const seen = Object.create(null);
  const dups = new Set();
  safeQueryAll(container, '.username-field').forEach(inp => {
    const v = (inp.value || '').trim();
    if (!v) return;
    if (seen[v]) dups.add(v); else seen[v] = 1;
  });
  dupWarnEl.textContent = dups.size ? ('duplicate usernames: ' + Array.from(dups).sort().join(', ')) : '';
}

function syncLiveHighlight(row) {
  if (!row) return;
  const liveSel = row.querySelector('.live-cell select');
  if (!liveSel) return;
  const on = (liveSel.value === 'true' || liveSel.value === '1');
  row.classList.toggle('live-on',  on);
  row.classList.toggle('live-off', !on);
  row.setAttribute('data-live', on ? 'true' : 'false');
}

function applyLiveFilter(mode) {
  if (!container) return;
  safeQueryAll(container, '.user-row').forEach(r => {
    const v = r.getAttribute('data-live') || 'true';
    r.style.display = (mode === 'all' || v === mode) ? '' : 'none';
  });
}

/* --------------------------- Event: Top Buttons ---------------------------- */
if (addProviderBtn && container) {
  addProviderBtn.addEventListener('click', function () {
    const tplEl = document.getElementById('providerTemplate');
    if (!tplEl) return;
    const template = tplEl.innerHTML;
    const index = safeQueryAll(container, '.provider-fieldset').length;
    const html = template.replace(/__INDEX__/g, String(index)).replace(/__NUM__/g, String(index + 1));
    container.insertAdjacentHTML('beforeend', html);
    updateProviderIndices();
  });
}

if (refreshRepoBtn) {
  refreshRepoBtn.addEventListener('click', function () {
    const url = new URL(window.location.href);
    url.searchParams.set('action', 'refresh');
    window.location.href = url.toString();
  });
}

if (expandAllBtn && container) {
  expandAllBtn.addEventListener('click', function () {
    safeQueryAll(container, '.provider-fieldset').forEach(c => c.classList.remove('collapsed'));
  });
}
if (collapseAllBtn && container) {
  collapseAllBtn.addEventListener('click', function () {
    safeQueryAll(container, '.provider-fieldset').forEach(c => c.classList.add('collapsed'));
  });
}

if (liveAllBtn)  liveAllBtn.addEventListener('click', () => applyLiveFilter('all'));
if (liveOnBtn)   liveOnBtn.addEventListener('click',  () => applyLiveFilter('true'));
if (liveOffBtn)  liveOffBtn.addEventListener('click', () => applyLiveFilter('false'));

/* --------------------- Event: Provider Grid Delegation --------------------- */
if (container) {
  container.addEventListener('click', function (e) {
    const btn = e.target.closest('button');
    if (!btn) return;

    if (btn.classList.contains('remove-provider-btn')) {
      if (confirm('Remove this provider?')) {
        const card = btn.closest('.provider-fieldset');
        if (card) card.remove();
        updateProviderIndices();
      }
      return;
    }

    if (btn.classList.contains('toggle-btn')) {
      const card = btn.closest('.provider-fieldset');
      if (card) card.classList.toggle('collapsed');
      return;
    }

    if (btn.classList.contains('add-user-btn')) {
      const card = btn.closest('.provider-fieldset');
      if (!card) return;
      const pIndex = card.getAttribute('data-index') || '0';
      const usersContainer = card.querySelector('.users-container');
      if (!usersContainer) return;

      const uIndex = safeQueryAll(usersContainer, '.user-row').length;
      const userTplEl = document.getElementById('userTemplate');
      if (!userTplEl) return;
      const html = userTplEl.innerHTML
        .replace(/__PINDEX__/g, pIndex)
        .replace(/__UINDEX__/g, String(uIndex));

      // Insert at top for visibility
      usersContainer.insertAdjacentHTML('afterbegin', html);
      const newRow = usersContainer.firstElementChild;
      syncLiveHighlight(newRow);
      const firstInput = newRow ? newRow.querySelector('input[type="text"]') : null;
      if (firstInput) { firstInput.focus(); newRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }

      refreshCounts(card);
      refreshSummary();
      return;
    }

    if (btn.classList.contains('remove-user-btn')) {
      if (confirm('Remove this user?')) {
        const card = btn.closest('.provider-fieldset');
        const row  = btn.closest('.user-row');
        if (row) row.remove();
        refreshCounts(card);
        refreshSummary();
      }
      return;
    }

    if (btn.classList.contains('add-credential-btn')) {
      const card = btn.closest('.provider-fieldset');
      if (!card) return;
      const pIndex = card.getAttribute('data-index') || '0';
      const cc = card.querySelector('.credentials-container');
      if (!cc) return;
      const cIndex = cc.children.length;
      const credTplEl = document.getElementById('credentialTemplate');
      if (!credTplEl) return;

      const html = credTplEl.innerHTML
        .replace(/__PINDEX__/g, pIndex)
        .replace(/__CINDEX__/g, String(cIndex));
      cc.insertAdjacentHTML('beforeend', html);

      refreshCounts(card);
      refreshSummary();
      return;
    }

    if (btn.classList.contains('remove-credential-btn')) {
      if (confirm('Remove this credential?')) {
        const card = btn.closest('.provider-fieldset');
        const row  = btn.closest('.credential-row');
        if (row) row.remove();
        refreshCounts(card);
        refreshSummary();
      }
      return;
    }
  });

  container.addEventListener('input', function (e) {
    const el = e.target;
    if (el.matches('.username-field')) dedupeCheck();
    if (el.matches('input[name*="[name]"]')) {
      const card = el.closest('.provider-fieldset');
      if (card) card.setAttribute('data-name', el.value.trim());
    }
    if (el.matches('input[name*="[url]"]')) {
      const card = el.closest('.provider-fieldset');
      if (card) card.setAttribute('data-url', el.value.trim());
    }
  });

  container.addEventListener('change', function (e) {
    const el = e.target;
    if (el.closest('.live-cell')) {
      const row = el.closest('.user-row');
      syncLiveHighlight(row);
    }
  });
}

if (filterInput && container) {
  filterInput.addEventListener('input', function () {
    const q = (this.value || '').toLowerCase().trim();
    safeQueryAll(container, '.provider-fieldset').forEach(card => {
      const n = (card.getAttribute('data-name') || '').toLowerCase();
      const u = (card.getAttribute('data-url')  || '').toLowerCase();
      card.style.display = (!q || n.includes(q) || u.includes(q)) ? '' : 'none';
    });
  });
}

/* ----------------------------- Initial Render ------------------------------ */
if (container) {
  safeQueryAll(container, '.user-row').forEach(syncLiveHighlight);
  safeQueryAll(container, '.provider-fieldset').forEach(refreshCounts);
  refreshSummary();
}

/* --------------------------- Live Sessions Panel --------------------------- */
// These features are enabled only if the Live Sessions card exists.
let sessionsMode = 'all'; // 'all' | 'true' | 'false'  (true = has a channel #)
function renderSessions(list) {
  if (!liveTable || !liveSub) return;
  const tbody = liveTable.querySelector('tbody');
  if (!tbody) return;
  tbody.innerHTML = '';

  let shown = 0;
  list.forEach(s => {
    const isWatching = !!s.channel_number;
    const pass = (sessionsMode === 'all') ? true :
                 (sessionsMode === 'true' ? isWatching : !isWatching);
    if (!pass) return;

    const tr = document.createElement('tr');
    const chan = s.channel_number ? `${s.channel_number}${s.channel_name ? ' — ' + s.channel_name : ''}` : '';
    const prog = s.program_title || '';
    const since = s.since ? new Date(s.since * 1000).toLocaleString() : '';
    const ua = (s.iptv_agents || s.user_agent || '');

    tr.innerHTML = `
      ${/* Optional proxy_name column—include if your thead has it */''}
      ${liveTable.querySelector('thead tr th')?.textContent?.toLowerCase()?.includes('proxy') ? `<td>${s.proxy_name || ''}</td>` : ''}
      <td>${s.username || ''}</td>
      <td>${s.ip || ''}</td>
      <td>${s.provider || ''}</td>
      <td>${chan}</td>
      <td>${prog}</td>
      <td>${since}</td>
      <td title="${ua.replace(/"/g,'&quot;')}">${ua.slice(0, 80)}</td>
    `;
    tbody.appendChild(tr);
    shown++;
  });

  liveSub.textContent = `${shown} session${shown === 1 ? '' : 's'} displayed`;
}

async function fetchSessions() {
  if (!liveTable) return;
  try {
    const res = await fetch('api/status.php', { cache: 'no-store' });
    const data = await res.json();
    if (!data || !data.ok) throw new Error(data && data.error ? data.error : 'status error');
    renderSessions(Array.isArray(data.sessions) ? data.sessions : []);
  } catch (err) {
    // Silently log to console; UI remains unchanged
    console.error('status fetch failed:', err);
  }
}

// Live Sessions controls (if present)
if (refreshNowBtn)  refreshNowBtn.addEventListener('click', fetchSessions);
if (liveShowAllBtn) liveShowAllBtn.addEventListener('click', () => { sessionsMode = 'all';  fetchSessions(); });
if (liveShowOnBtn)  liveShowOnBtn.addEventListener('click', () => { sessionsMode = 'true'; fetchSessions(); });
if (liveShowOffBtn) liveShowOffBtn.addEventListener('click', () => { sessionsMode = 'false';fetchSessions(); });

// Start polling only if Live Sessions table exists
if (liveTable) {
  fetchSessions();
  setInterval(fetchSessions, 10000); // every 10s
}
