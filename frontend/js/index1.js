const API_PAPERS  = '/research-repo/api/papers.php';
const API_AUTH    = '/research-repo/api/auth.php';

// ── Auth guard — redirect to login if not logged in ───────────────────────
const token = localStorage.getItem('mira_token');
const user  = JSON.parse(localStorage.getItem('mira_user') || 'null');

if (!token || !user) {
  window.location.href = '/research-repo/frontend/login.html';
}

// ── Populate navbar user info ─────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
  document.getElementById('userName').textContent  = user.name;
  document.getElementById('userRole').textContent  = user.role;
  document.getElementById('userAvatar').textContent = user.name.charAt(0).toUpperCase();
// Show upload button only for lecturers and admins

  if (user.role === 'student') {
    document.getElementById('btnUpload').style.display = 'none';
    document.getElementById('navUpload').style.display = 'none';
  }
// ── Logout ────────────────────────────────────────────────────────────────
function logout() {
  localStorage.removeItem('mira_token');
  localStorage.removeItem('mira_user');
  window.location.href = '/research-repo/frontend/login.html';
}

// ── Load papers from API ──────────────────────────────────────────────────
async function loadPapers(tag = '', search = '') {
  const grid = document.getElementById('papersGrid');
  grid.innerHTML = `
    <div class="spinner" style="grid-column:1/-1">
      <div class="spinner-ring"></div>
      Loading papers...
    </div>`;

  try {
    let url = API_PAPERS + '?action=list';
    if (tag)    url += `&tag=${encodeURIComponent(tag)}`;
    if (search) url += `&search=${encodeURIComponent(search)}`;

    const res  = await fetch(url, {
      headers: { 'Authorization': `Bearer ${token}` }
    });
    const data = await res.json();

    if (!res.ok) {
      grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1">
        <div class="empty-icon">⚠</div>
        <h3>Could not load papers</h3>
        <p>${data.error || 'Unknown error'}</p>
      </div>`;
      return;
    }

    const papers = data.papers || [];
    document.getElementById('totalPapers').textContent = papers.length;
    document.getElementById('totalUsers').textContent  = data.total_users || 0;
    document.getElementById('totalTags').textContent   = data.total_tags  || 0;
    renderPapers(papers);

  } catch (err) {
    grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1">
      <div class="empty-icon">⚠</div>
      <h3>Connection error</h3>
      <p>Could not reach the server.</p>
    </div>`;
  }
}

// ── Render paper cards ────────────────────────────────────────────────────
function renderPapers(papers) {
  const grid = document.getElementById('papersGrid');

  if (papers.length === 0) {
    grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1">
      <div class="empty-icon">📄</div>
      <h3>No papers found</h3>
      <p>No research papers have been uploaded yet.</p>
      ${user.role !== 'student'
        ? '<a href="/research-repo/frontend/upload.html" class="btn btn-primary">Upload First Paper</a>'
        : ''}
    </div>`;
    return;
  }

  grid.innerHTML = papers.map(p => `
    <a class="paper-card" href="/research-repo/frontend/paper.html?id=${p.id}">
      <div class="paper-title">${escHtml(p.title)}</div>
      <div class="paper-authors">${escHtml(p.authors || 'Unknown authors')}</div>
      <div class="paper-abstract">${escHtml(p.abstract || p.summary || 'No abstract available.')}</div>
      <div class="paper-footer">
        <div class="paper-tags">
          ${(p.tags || []).slice(0,3).map(t =>
            `<span class="tag">${escHtml(t)}</span>`
          ).join('')}
        </div>
        <span class="paper-date">${formatDate(p.uploaded_at)}</span>
      </div>
    </a>
  `).join('');
}

// ── Search ────────────────────────────────────────────────────────────────
function handleSearch(e) {
  e.preventDefault();
  const q = document.getElementById('searchInput').value.trim();
  if (q) {
    window.location.href = `/research-repo/frontend/search.html?q=${encodeURIComponent(q)}`;
  }
}

// ── Filter by tag ─────────────────────────────────────────────────────────
function filterByTag() {
  const tag = document.getElementById('tagFilter').value;
  loadPapers(tag);
}

// ── Helpers ───────────────────────────────────────────────────────────────
function escHtml(str) {
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

// ── Load tags for filter dropdown ─────────────────────────────────────────
async function loadTags() {
  try {
    const res  = await fetch('/research-repo/api/tags.php', {
      headers: { 'Authorization': `Bearer ${token}` }
    });
    const data = await res.json();
    const sel  = document.getElementById('tagFilter');
    (data.tags || []).forEach(t => {
      const opt = document.createElement('option');
      opt.value = t.name;
      opt.textContent = t.name;
      sel.appendChild(opt);
    });
  } catch (e) { /* tags are optional */ }
}

// ── Init ──────────────────────────────────────────────────────────────────
loadTags();
loadPapers()