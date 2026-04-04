const API_SEARCH = '/research-repo/api/search.php';

// ── Auth guard ────────────────────────────────────────────────────────────
const token = localStorage.getItem('mira_token');
const user  = JSON.parse(localStorage.getItem('mira_user') || 'null');
if (!token || !user) window.location.href = '/research-repo/frontend/login.html';

// ── Navbar ────────────────────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
  document.getElementById('userName').textContent   = user.name;
  document.getElementById('userRole').textContent   = user.role;
  document.getElementById('userAvatar').textContent = user.name.charAt(0).toUpperCase();

  if (user.role === 'student') {
    document.getElementById('navUpload').style.display = 'none';
  }
});

function logout() {
  localStorage.removeItem('mira_token');
  localStorage.removeItem('mira_user');
  window.location.href = '/research-repo/frontend/login.html';
}

// ── Pre-fill query from URL param ─────────────────────────────────────────
const params = new URLSearchParams(window.location.search);
const q      = params.get('q');
if (q) {
  document.getElementById('searchInput').value = q;
  performSearch(q);
}

// ── Handle search form ────────────────────────────────────────────────────
function handleSearch(e) {
  e.preventDefault();
  const query = document.getElementById('searchInput').value.trim();
  if (!query) return;
  // Update URL
  window.history.pushState({}, '', `?q=${encodeURIComponent(query)}`);
  performSearch(query);
}

function searchSuggestion(text) {
  document.getElementById('searchInput').value = text;
  window.history.pushState({}, '', `?q=${encodeURIComponent(text)}`);
  performSearch(text);
}

// ── Perform search ────────────────────────────────────────────────────────
async function performSearch(query) {
  const resultsEl = document.getElementById('results');
  const headerEl  = document.getElementById('resultsHeader');
  const btn       = document.getElementById('searchBtn');

  btn.disabled    = true;
  btn.textContent = 'Searching...';
  headerEl.style.display = 'none';

  resultsEl.innerHTML = `
    <div class="spinner">
      <div class="spinner-ring"></div>
      Searching through papers...
    </div>`;

  try {
    const res  = await fetch(API_SEARCH, {
      method:  'POST',
      headers: {
        'Content-Type':  'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: JSON.stringify({ query })
    });

    const data = await res.json();

    if (!res.ok) {
      resultsEl.innerHTML = `
        <div class="empty-state">
          <div class="empty-icon">⚠</div>
          <h3>Search failed</h3>
          <p>${data.error || 'Unknown error — status ' + res.status}</p>
        </div>`;
      return;
    }

    renderResults(data.results, query);

  } catch (err) {
    resultsEl.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">⚠</div>
        <h3>Connection error</h3>
        <p>${err.message}</p>
      </div>`;
  }

  btn.disabled    = false;
  btn.textContent = 'Search';
}

// ── Render results ────────────────────────────────────────────────────────
function renderResults(results, query) {
  const resultsEl = document.getElementById('results');
  const headerEl  = document.getElementById('resultsHeader');

  headerEl.style.display = 'flex';
  document.getElementById('resultsTitle').textContent =
    `Results for "${query}"`;
  document.getElementById('resultsCount').textContent =
    `${results.length} paper${results.length !== 1 ? 's' : ''} found`;

  if (results.length === 0) {
    resultsEl.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">🔍</div>
        <h3>No matching papers found</h3>
        <p>Try different keywords or a broader topic.</p>
      </div>`;
    return;
  }

  resultsEl.innerHTML = results.map(r => {
    const pct        = Math.round(r.score * 100);
    const scoreClass = pct >= 70 ? 'high' : pct >= 50 ? 'medium' : 'low';
    const tags       = (r.tags || []).slice(0, 3)
      .map(t => `<span class="tag">${escHtml(t)}</span>`).join('');

    return `
      <a class="result-card" href="/research-repo/frontend/paper.html?id=${r.paper_id}">
        <div class="result-top">
          <div class="result-title">${escHtml(r.title)}</div>
          <span class="score-badge ${scoreClass}">${pct}% match</span>
        </div>
        <div class="result-authors">${escHtml(r.authors || 'Unknown authors')}</div>
        <div class="result-excerpt">${escHtml(r.excerpt)}</div>
        <div class="result-footer">
          <div class="result-tags">${tags}</div>
          <span class="result-date">${formatDate(r.uploaded_at)}</span>
        </div>
      </a>`;
  }).join('');
}

// ── Helpers ───────────────────────────────────────────────────────────────
function escHtml(str) {
  if (!str) return '';
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}

function formatDate(str) {
  if (!str) return '';
  return new Date(str).toLocaleDateString('en-GB', {
    day: 'numeric', month: 'short', year: 'numeric'
  });
}

// ── Enter key ─────────────────────────────────────────────────────────────
document.getElementById('searchInput').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    handleSearch(e);
  }
});