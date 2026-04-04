const API_PAPERS = '/research-repo/api/papers.php';
const API_CHAT   = '/research-repo/api/chat.php';

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

// ── Get paper ID from URL ─────────────────────────────────────────────────
const params  = new URLSearchParams(window.location.search);
const paperId = params.get('id');
if (!paperId) window.location.href = '/research-repo/frontend/index.html';

// ── Chat state ────────────────────────────────────────────────────────────
let convId    = null;
let isTyping  = false;

// ── Load paper ────────────────────────────────────────────────────────────
async function loadPaper() {
  try {
    const res  = await fetch(`${API_PAPERS}?action=get&id=${paperId}`, {
      headers: { 'Authorization': `Bearer ${token}` }
    });
    const data = await res.json();

    if (!res.ok) {
      document.getElementById('paperContent').innerHTML =
        `<div class="alert alert-error">Could not load paper: ${data.error}</div>`;
      return;
    }

    const p = data.paper;

    // Populate paper details
    document.title = `MIRA — ${p.title}`;
    document.getElementById('paperTitle').textContent    = p.title;
    document.getElementById('paperAuthors').textContent  = p.authors || 'Unknown authors';
    document.getElementById('paperDate').textContent     = formatDate(p.uploaded_at);
    document.getElementById('paperUploader').textContent = `Uploaded by ${p.uploader}`;
    document.getElementById('paperAbstract').textContent = p.abstract || 'No abstract available.';
    document.getElementById('paperSummary').textContent  = p.summary  || 'Summary not available.';

    // Tags
    const tagsEl = document.getElementById('paperTags');
    if (p.tags && p.tags.length > 0) {
      tagsEl.innerHTML = p.tags.map(t => `<span class="tag">${escHtml(t)}</span>`).join('');
    } else {
      tagsEl.innerHTML = '<span style="font-size:13px;color:var(--text-muted)">No tags</span>';
    }

    // Download link
    document.getElementById('downloadBtn').href =
      `/research-repo/uploads/${p.file_path}`;

    // Show delete button for admins
    if (user.role === 'admin') {
      document.getElementById('deleteBtn').style.display = 'inline-flex';
    }

  } catch (err) {
    document.getElementById('paperContent').innerHTML =
      `<div class="alert alert-error">Connection error loading paper.</div>`;
  }
}

// ── Delete paper ──────────────────────────────────────────────────────────
async function deletePaper() {
  if (!confirm('Are you sure you want to delete this paper? This cannot be undone.')) return;

  const res = await fetch(`${API_PAPERS}?id=${paperId}`, {
    method:  'DELETE',
    headers: { 'Authorization': `Bearer ${token}` }
  });

  if (res.ok) {
    window.location.href = '/research-repo/frontend/index.html';
  } else {
    alert('Failed to delete paper.');
  }
}

// ── Chat ──────────────────────────────────────────────────────────────────
function askQuestion(text) {
  document.getElementById('chatInput').value = text;
  sendMessage();
}

async function sendMessage() {
  const input = document.getElementById('chatInput');
  const msg   = input.value.trim();
  if (!msg || isTyping) return;

  // Hide welcome screen
  document.getElementById('chatWelcome').style.display = 'none';

  // Append user bubble
  appendBubble(msg, 'user');
  input.value = '';
  isTyping    = true;

  const btn = document.getElementById('sendBtn');
  btn.disabled = true;

  // Show typing indicator
  const typingId = appendBubble('Thinking...', 'typing');

  try {
    const res  = await fetch(API_CHAT, {
      method:  'POST',
      headers: {
        'Content-Type':  'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: JSON.stringify({
        paper_id: parseInt(paperId),
        conv_id:  convId,
        message:  msg,
      })
    });

    const data = await res.json();

    // Remove typing indicator
    document.getElementById(typingId)?.remove();

    if (!res.ok) {
      appendBubble('Sorry, I could not process that. Please try again.', 'assistant');
    } else {
      convId = data.conv_id;
      appendBubble(data.reply, 'assistant');
    }

  } catch (err) {
    document.getElementById(typingId)?.remove();
    appendBubble('Connection error. Please check your internet connection.', 'assistant');
  }

  isTyping     = false;
  btn.disabled = false;
  input.focus();
}

function appendBubble(text, type) {
  const messages = document.getElementById('chatMessages');
  const id       = 'bubble-' + Date.now();
  const div      = document.createElement('div');
  div.id         = id;
  div.className  = `chat-bubble ${type}`;

  if (type === 'assistant') {
    div.innerHTML = marked.parse(text);
  } else {
    div.textContent = text;
  }

  messages.appendChild(div);
  messages.scrollTop = messages.scrollHeight;
  return id;
}

// ── Enter key to send ─────────────────────────────────────────────────────
document.getElementById('chatInput').addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
});

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

// ── Init ──────────────────────────────────────────────────────────────────
loadPaper();