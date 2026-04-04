const API_UPLOAD = '/research-repo/api/upload.php';

// ── Auth guard ────────────────────────────────────────────────────────────
const token = localStorage.getItem('mira_token');
const user  = JSON.parse(localStorage.getItem('mira_user') || 'null');

if (!token || !user) {
  window.location.href = '/research-repo/frontend/login.html';
}

if (user.role === 'student') {
  window.location.replace('/research-repo/frontend/index.html');
}

// Remove auth guard — page is visible now
document.getElementById('authGuard').remove();

// ── Navbar ────────────────────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
  document.getElementById('authGuard')?.remove();
  document.getElementById('userName').textContent   = user.name;
  document.getElementById('userRole').textContent   = user.role;
  document.getElementById('userAvatar').textContent = user.name.charAt(0).toUpperCase();
});

function logout() {
  localStorage.removeItem('mira_token');
  localStorage.removeItem('mira_user');
  window.location.href = '/research-repo/frontend/login.html';
}

// ── Drop zone ─────────────────────────────────────────────────────────────
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('pdfFile');
let selectedFile = null;

dropZone.addEventListener('click', () => fileInput.click());

dropZone.addEventListener('dragover', (e) => {
  e.preventDefault();
  dropZone.classList.add('dragover');
});

dropZone.addEventListener('dragleave', () => {
  dropZone.classList.remove('dragover');
});

dropZone.addEventListener('drop', (e) => {
  e.preventDefault();
  dropZone.classList.remove('dragover');
  const file = e.dataTransfer.files[0];
  if (file) setFile(file);
});

fileInput.addEventListener('change', () => {
  if (fileInput.files[0]) setFile(fileInput.files[0]);
});

function setFile(file) {
  if (file.type !== 'application/pdf') {
    showAlert('Only PDF files are allowed.', 'error');
    return;
  }
  if (file.size > 10 * 1024 * 1024) {
    showAlert('File is too large. Maximum size is 10MB.', 'error');
    return;
  }
  selectedFile = file;
  dropZone.classList.add('has-file');
  document.getElementById('fileSelected').textContent = file.name;
  document.getElementById('dropPrompt').style.display = 'none';
}

// ── Progress steps ────────────────────────────────────────────────────────
const steps = ['stepUpload', 'stepParse', 'stepChunk', 'stepEmbed', 'stepSummary'];

function setStep(index) {
  steps.forEach((id, i) => {
    const el = document.getElementById(id);
    if (i < index)       el.className = 'progress-step done';
    else if (i === index) el.className = 'progress-step active';
    else                  el.className = 'progress-step';
  });
}

// ── Show alert ────────────────────────────────────────────────────────────
function showAlert(text, type) {
  const el = document.getElementById('uploadAlert');
  el.textContent = text;
  el.className   = `alert alert-${type}`;
  el.style.display = 'block';
}

// ── Handle submit ─────────────────────────────────────────────────────────
async function handleUpload(e) {
  e.preventDefault();

  if (!selectedFile) {
    showAlert('Please select a PDF file to upload.', 'error');
    return;
  }

  const title    = document.getElementById('title').value.trim();
  const authors  = document.getElementById('authors').value.trim();
  const abstract = document.getElementById('abstract').value.trim();
  const tags     = document.getElementById('tags').value.trim();

  if (!title) {
    showAlert('Please enter the paper title.', 'error');
    return;
  }

  // Show progress
  const btn      = document.getElementById('submitBtn');
  const progress = document.getElementById('progressBox');
  btn.disabled   = true;
  btn.textContent = 'Processing...';
  progress.style.display = 'block';
  document.getElementById('uploadAlert').style.display = 'none';

  setStep(0);

  const formData = new FormData();
  formData.append('pdf',      selectedFile);
  formData.append('title',    title);
  formData.append('authors',  authors);
  formData.append('abstract', abstract);
  formData.append('tags',     tags);

  // Simulate step progression while uploading
  setTimeout(() => setStep(1), 1000);
  setTimeout(() => setStep(2), 3000);
  setTimeout(() => setStep(3), 5000);

  try {
    const res  = await fetch(API_UPLOAD, {
      method:  'POST',
      headers: { 'Authorization': `Bearer ${token}` },
      body:    formData,
    });

    const data = await res.json();

    if (!res.ok) {
      showAlert(data.error || 'Upload failed.', 'error');
      progress.style.display = 'none';
    } else {
      setStep(4);
      setTimeout(() => setStep(5), 1000);
      showAlert(
        `Paper uploaded successfully! ${data.chunks} chunks processed.`,
        'success'
      );
      setTimeout(() => {
        window.location.href = '/research-repo/frontend/index.html';
      }, 2000);
    }

  } catch (err) {
    showAlert('Connection error. Please try again.', 'error');
    progress.style.display = 'none';
  }

  btn.disabled = false;
  btn.textContent = 'Upload Paper';
}