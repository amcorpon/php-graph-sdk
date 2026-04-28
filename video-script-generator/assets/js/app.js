/**
 * VideoScript AI — Main frontend application
 */

'use strict';

// ---------------------------------------------------------------------------
// API helpers
// ---------------------------------------------------------------------------
async function apiFetch(endpoint, body = null, method = 'POST') {
  const opts = { method, headers: { 'Content-Type': 'application/json' } };
  if (body) opts.body = JSON.stringify(body);
  const res  = await fetch(endpoint, opts);
  return res.json();
}

function showError(elId, msg) {
  const el = document.getElementById(elId);
  if (!el) return;
  el.textContent  = msg;
  el.style.display = 'block';
  el.className    = 'alert alert-error';
  setTimeout(() => { el.style.display = 'none'; }, 6000);
}

function formatBytes(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1024 / 1024).toFixed(1) + ' MB';
}

function formatDate(str) {
  if (!str) return '';
  return new Date(str).toLocaleDateString(undefined, { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
}

function fileIcon(name) {
  if (/\.mp4|\.mov|\.webm/i.test(name)) return '🎬';
  if (/\.mp3|\.wav|\.ogg/i.test(name)) return '🎵';
  if (/\.jpg|\.jpeg|\.png|\.webp/i.test(name)) return '🖼️';
  if (/\.txt/i.test(name)) return '📄';
  return '📁';
}

// ---------------------------------------------------------------------------
// Projects list
// ---------------------------------------------------------------------------
async function loadProjects() {
  const container = document.getElementById('projects-list');
  if (!container) return;

  container.innerHTML = '<div class="loading-placeholder">Loading...</div>';
  const data = await apiFetch('api/get_projects.php', null, 'GET');
  const list = data.projects || [];

  if (!list.length) {
    container.innerHTML = '<div class="loading-placeholder">No projects yet — create your first one above!</div>';
    return;
  }

  container.innerHTML = list.map(p => projectCardHTML(p)).join('');
}

function projectCardHTML(p) {
  const total    = parseInt(p.total_sections) || 0;
  const done     = parseInt(p.done_sections)  || 0;
  const pct      = total > 0 ? Math.round(done / total * 100) : 0;
  const statusCls = `status-${p.status}`;

  return `
<div class="project-card" onclick="openProject(${p.id})">
  <h3 title="${p.title}">${p.title}</h3>
  <div class="project-card-meta">
    <span class="status-badge ${statusCls}">${p.status.replace(/_/g,' ')}</span>
    <span>⏱ ${p.duration_minutes} min</span>
    <span>${p.ai_provider === 'both' ? '🤖 Both' : p.ai_provider === 'claude' ? '🟣 Claude' : '🔵 Gemini'}</span>
    <span>🔊 ${p.voice_provider}</span>
  </div>
  ${total > 0 ? `
  <div class="project-progress">
    <div class="project-progress-fill" style="width:${pct}%"></div>
  </div>` : ''}
  <div class="project-card-meta" style="margin-top:10px;margin-bottom:0">
    <span>${formatDate(p.created_at)}</span>
    <span style="margin-left:auto;color:var(--text-muted);font-size:.78rem">${total} sections</span>
  </div>
</div>`;
}

// ---------------------------------------------------------------------------
// Project modal
// ---------------------------------------------------------------------------
let _currentProjectId = null;
let _pollInterval     = null;

function openProject(project_id) {
  _currentProjectId = project_id;
  document.getElementById('project-modal').style.display = 'flex';
  loadProjectDetail(project_id);
}

function closeModal() {
  document.getElementById('project-modal').style.display = 'none';
  if (_pollInterval) { clearInterval(_pollInterval); _pollInterval = null; }
  loadProjects();
}

async function loadProjectDetail(project_id) {
  const box  = document.getElementById('modal-content');
  box.innerHTML = '<div class="loading-placeholder">Loading project...</div>';

  const data    = await apiFetch(`api/project_status.php?project_id=${project_id}`, null, 'GET');
  const project = data.project;
  const sections = data.sections || [];
  const files   = data.files || [];

  if (!project) { box.innerHTML = '<div class="alert alert-error">Project not found</div>'; return; }

  const isProcessing = ['generating','processing'].includes(project.status);
  const isScript     = project.status === 'script_ready';
  const isSearching  = project.status === 'searching_media';
  const isMediaReady = project.status === 'media_ready';
  const isCompleted  = project.status === 'completed';

  box.innerHTML = `
<div class="project-detail-header">
  <div class="project-detail-title">
    <h2>${project.title}</h2>
    <p>${project.theme} &middot; ${project.duration_minutes} min &middot; <span class="status-badge status-${project.status}">${project.status.replace(/_/g,' ')}</span></p>
  </div>
  <div class="action-bar" id="action-bar">
    ${actionBarHTML(project, sections)}
  </div>
</div>

<div class="tabs">
  <button class="tab-btn active" onclick="showTab('script')">📄 Script</button>
  <button class="tab-btn" onclick="showTab('media')">🖼 Media</button>
  ${isCompleted ? '<button class="tab-btn" onclick="showTab(\'files\')">📦 Files</button>' : ''}
  ${isProcessing ? '<button class="tab-btn" onclick="showTab(\'log\')">📋 Log</button>' : ''}
</div>

<div id="tab-script" class="tab-panel active">
  <div class="sections-list" id="sections-list">
    ${sections.length ? sections.map(s => sectionHTML(s)).join('') : '<p class="text-muted">No script generated yet.</p>'}
  </div>
</div>

<div id="tab-media" class="tab-panel">
  <div class="sections-list" id="media-sections-list">
    ${sections.filter(s => ['broll_image','broll_video'].includes(s.section_type)).map(s => mediaSectionHTML(s)).join('') || '<p class="text-muted">No B-ROLL sections found.</p>'}
  </div>
</div>

${isCompleted ? `
<div id="tab-files" class="tab-panel">
  <p class="text-muted" style="margin-bottom:16px">All files are named in sequence order — import them directly into your editor.</p>
  <div class="files-list">
    ${files.map(f => `
    <div class="file-item">
      <span class="file-icon">${fileIcon(f.name)}</span>
      <span class="file-name">${f.name}</span>
      <span class="file-size">${formatBytes(f.size)}</span>
      <a href="${f.url}" download class="btn btn-sm btn-outline">↓</a>
    </div>`).join('')}
  </div>
  ${files.length ? `<br><a href="api/download_zip.php?project_id=${project_id}" class="btn btn-primary">📦 Download All as ZIP</a>` : ''}
</div>` : ''}

<div id="tab-log" class="tab-panel">
  <div class="log-box" id="log-box">Loading log...</div>
</div>
`;

  // Auto-poll when processing
  if (isProcessing) {
    startPolling(project_id);
  }
}

function actionBarHTML(project, sections) {
  const s = project.status;
  let html = '';

  if (s === 'draft') {
    html += `<button class="btn btn-primary" onclick="generateScript(${project.id})">
               <span class="spinner" id="gen-spinner" style="display:none"></span> ✨ Generate Script
             </button>`;
  }
  if (s === 'script_ready') {
    html += `<button class="btn btn-primary" onclick="searchMedia(${project.id})">🔍 Search B-ROLL Media</button>`;
    html += `<button class="btn btn-outline" onclick="generateScript(${project.id})">↻ Regenerate</button>`;
  }
  if (s === 'searching_media' || s === 'media_ready') {
    const allConfirmed = sections.filter(s => ['broll_image','broll_video'].includes(s.section_type))
                                 .every(s => s.status === 'confirmed');
    if (allConfirmed) {
      html += `<button class="btn btn-primary" onclick="processProject(${project.id})">🚀 Process & Generate Audio</button>`;
    } else {
      html += `<button class="btn btn-outline" onclick="searchMedia(${project.id})">↻ Re-search Media</button>`;
      html += `<span class="chip">Select media for each B-ROLL section</span>`;
    }
  }
  if (s === 'processing') {
    html += `<span class="chip"><span class="spinner"></span> Processing...</span>`;
  }
  if (s === 'completed') {
    html += `<button class="btn btn-primary" onclick="showTab('files');loadFiles(${project.id})">📦 View Files</button>`;
    html += `<button class="btn btn-outline" onclick="generateScript(${project.id})">↻ Regenerate</button>`;
  }
  if (s === 'error') {
    html += `<button class="btn btn-outline" onclick="generateScript(${project.id})">↻ Retry</button>`;
  }

  html += `<button class="btn btn-danger btn-sm" onclick="deleteProject(${project.id})">🗑</button>`;
  return html;
}

// ---------------------------------------------------------------------------
// Section rendering
// ---------------------------------------------------------------------------
function sectionHTML(sec) {
  const typeColors = { narration: 'type-narration', broll_image: 'type-broll_image',
                       broll_video: 'type-broll_video', text_overlay: 'type-text_overlay' };
  const typeLabel  = { narration: '🎤 Narration', broll_image: '🖼 Image', broll_video: '🎬 Video', text_overlay: '📋 Text' };
  const dotClass   = { pending:'dot-pending', found:'dot-found', confirmed:'dot-confirmed', done:'dot-done', error:'dot-error' };

  const preview = sec.narration_text || sec.overlay_text || '';

  return `
<div class="section-item" id="sec-${sec.id}">
  <div class="section-header-row" onclick="toggleSection(${sec.id})">
    <span class="section-seq">${String(sec.sequence_number).padStart(3,'0')}</span>
    <span class="section-type-tag ${typeColors[sec.section_type]}">${typeLabel[sec.section_type]}</span>
    <span class="section-text">${preview}</span>
    <span class="section-dur">${sec.duration_seconds}s</span>
    <span class="section-status-dot ${dotClass[sec.status] || 'dot-pending'}" title="${sec.status}"></span>
  </div>
  <div class="section-body" id="sec-body-${sec.id}">
    ${sectionBodyHTML(sec)}
  </div>
</div>`;
}

function sectionBodyHTML(sec) {
  let html = '';

  if (sec.narration_text) {
    html += `<div style="margin-bottom:12px">
      <label>Narration</label>
      <p style="font-size:.9rem;color:var(--text);line-height:1.7">${sec.narration_text}</p>
    </div>`;
  }

  if (sec.section_type === 'text_overlay') {
    const bg = sec.overlay_bg_color || '#1a1a2e';
    const tc = sec.overlay_text_color || '#ffffff';
    html += `<div class="overlay-preview" style="background:${bg};color:${tc}">${(sec.overlay_text||'').replace(/\\n/g,'\n')}</div>`;
  }

  if (sec.search_terms) {
    const terms = JSON.parse(sec.search_terms || '[]');
    html += `<div style="margin-bottom:8px"><label>Search terms</label><div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px">
      ${terms.map(t => `<span class="chip">${t}</span>`).join('')}
    </div></div>`;
  }

  if (sec.description) {
    html += `<p style="font-size:.82rem;color:var(--text-muted)">${sec.description}</p>`;
  }

  if (sec.audio_file) {
    html += `<div style="margin-top:12px"><label>Audio</label>
      <audio controls style="width:100%;margin-top:6px"><source src="${sec.audio_file}"></audio></div>`;
  }

  if (sec.file_path && sec.section_type === 'broll_image') {
    html += `<img src="${sec.file_path}" style="width:100%;border-radius:6px;margin-top:10px;aspect-ratio:16/9;object-fit:cover">`;
  }

  return html || '<p class="text-muted">No content</p>';
}

function toggleSection(id) {
  const body = document.getElementById(`sec-body-${id}`);
  if (body) body.classList.toggle('open');
}

// ---------------------------------------------------------------------------
// Media section rendering
// ---------------------------------------------------------------------------
function mediaSectionHTML(sec) {
  const options = JSON.parse(sec.media_options || '[]');
  const typeLabel = sec.section_type === 'broll_image' ? '🖼 Image' : '🎬 Video';

  let optionsHTML = '';
  if (options.length) {
    optionsHTML = `<div class="media-options">
      ${options.map((opt, i) => {
        const selected = sec.selected_option == i && sec.status === 'confirmed';
        return `<div class="media-option ${selected ? 'selected' : ''}" onclick="selectMedia(${sec.id}, ${i})" title="${opt.alt || opt.description || ''}">
          <img src="${opt.thumb}" loading="lazy" alt="${opt.alt || ''}">
          ${opt.type === 'video' ? `<span class="video-badge">▶ ${opt.duration}s</span>` : ''}
          <div class="media-option-label">${opt.alt || '#'+opt.id}</div>
        </div>`;
      }).join('')}
    </div>`;
  } else if (sec.status === 'found' || sec.status === 'confirmed') {
    optionsHTML = '<p class="text-muted" style="font-size:.85rem">No media options found for this section.</p>';
  } else {
    optionsHTML = '<p class="text-muted" style="font-size:.85rem">Media not searched yet.</p>';
  }

  return `
<div class="section-item" id="media-sec-${sec.id}">
  <div class="section-header-row" onclick="toggleMediaSection(${sec.id})">
    <span class="section-seq">${String(sec.sequence_number).padStart(3,'0')}</span>
    <span class="section-type-tag ${sec.section_type === 'broll_image' ? 'type-broll_image' : 'type-broll_video'}">${typeLabel}</span>
    <span class="section-text">${sec.description || sec.narration_text || ''}</span>
    <span class="section-dur">${sec.duration_seconds}s</span>
    <span class="section-status-dot ${sec.status === 'confirmed' ? 'dot-confirmed' : sec.status === 'found' ? 'dot-found' : 'dot-pending'}" title="${sec.status}"></span>
  </div>
  <div class="section-body" id="media-sec-body-${sec.id}" style="padding-top:16px;">
    ${sec.status === 'confirmed' && sec.media_thumb
      ? `<div style="margin-bottom:12px;padding:8px;background:rgba(16,185,129,.1);border:1px solid var(--success);border-radius:6px;font-size:.82rem;color:var(--success)">
           ✅ Confirmed: <img src="${sec.media_thumb}" style="height:32px;border-radius:3px;vertical-align:middle;margin-left:6px">
         </div>` : ''}
    ${optionsHTML}
    <div style="display:flex;gap:8px;margin-top:8px">
      <button class="btn btn-sm btn-outline" onclick="researchSection(${sec.id}, ${_currentProjectId})">↻ New Search</button>
    </div>
  </div>
</div>`;
}

function toggleMediaSection(id) {
  const body = document.getElementById(`media-sec-body-${id}`);
  if (body) body.classList.toggle('open');
}

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------
async function generateScript(project_id) {
  const btn = document.querySelector('#action-bar .btn-primary');
  if (btn) btn.disabled = true;

  const data = await apiFetch('api/generate_script.php', { project_id });
  if (data.error) {
    alert('Error: ' + data.error);
    if (btn) btn.disabled = false;
    return;
  }
  loadProjectDetail(project_id);
}

async function searchMedia(project_id) {
  const box = document.getElementById('modal-content');
  const bar = document.getElementById('action-bar');
  if (bar) bar.innerHTML = '<span class="chip"><span class="spinner"></span> Searching Pexels...</span>';

  const data = await apiFetch('api/search_media.php', { project_id });
  if (data.error) { alert('Error: ' + data.error); return; }

  loadProjectDetail(project_id);
  showTab('media');
}

async function selectMedia(section_id, option_index) {
  const data = await apiFetch('api/confirm_media.php', { section_id, option_index });
  if (data.error) { alert('Error: ' + data.error); return; }

  // Update UI locally
  const container = document.getElementById(`media-sec-${section_id}`);
  if (container) {
    container.querySelectorAll('.media-option').forEach((el, i) => {
      el.classList.toggle('selected', i === option_index);
    });
    const dot = container.querySelector('.section-status-dot');
    if (dot) { dot.className = 'section-status-dot dot-confirmed'; dot.title = 'confirmed'; }

    // Add confirmation banner if not already there
    const body = document.getElementById(`media-sec-body-${section_id}`);
    if (body && data.chosen) {
      const existing = body.querySelector('.confirmed-banner');
      if (existing) existing.remove();
      const banner = document.createElement('div');
      banner.className = 'confirmed-banner';
      banner.style.cssText = 'margin-bottom:12px;padding:8px;background:rgba(16,185,129,.1);border:1px solid var(--success);border-radius:6px;font-size:.82rem;color:var(--success)';
      banner.innerHTML = `✅ Confirmed <img src="${data.chosen.thumb}" style="height:32px;border-radius:3px;vertical-align:middle;margin-left:6px">`;
      body.prepend(banner);
    }
  }

  // Check if all broll confirmed → update action bar
  loadProjectActionBar();
}

async function loadProjectActionBar() {
  const data = await apiFetch(`api/project_status.php?project_id=${_currentProjectId}`, null, 'GET');
  const bar  = document.getElementById('action-bar');
  if (bar && data.project) {
    bar.innerHTML = actionBarHTML(data.project, data.sections || []);
  }
}

async function processProject(project_id) {
  const data = await apiFetch('api/process_project.php', { project_id });
  if (data.error) { alert('Error: ' + data.error); return; }
  loadProjectDetail(project_id);
  startPolling(project_id);
}

function startPolling(project_id) {
  if (_pollInterval) clearInterval(_pollInterval);
  _pollInterval = setInterval(async () => {
    const data = await apiFetch(`api/project_status.php?project_id=${project_id}`, null, 'GET');
    if (!data.project) return;

    // Update progress
    const prog = document.getElementById('progress-fill');
    if (prog) prog.style.width = data.progress + '%';

    // Update log
    const logBox = document.getElementById('log-box');
    if (logBox) {
      const logData = await apiFetch(`api/get_log.php?project_id=${project_id}`, null, 'GET');
      logBox.textContent = logData.log || '';
      logBox.scrollTop   = logBox.scrollHeight;
    }

    if (!['generating','processing'].includes(data.project.status)) {
      clearInterval(_pollInterval);
      _pollInterval = null;
      loadProjectDetail(project_id);
    }
  }, 3000);
}

async function researchSection(section_id, project_id) {
  // Reset section status and re-search
  await apiFetch('api/search_media.php', { project_id, section_id });
  loadProjectDetail(project_id);
  showTab('media');
}

async function deleteProject(project_id) {
  if (!confirm('Delete this project and all its files?')) return;
  await apiFetch('api/delete_project.php', { project_id });
  closeModal();
}

// ---------------------------------------------------------------------------
// Tabs
// ---------------------------------------------------------------------------
function showTab(name) {
  document.querySelectorAll('.tab-btn').forEach(b => {
    b.classList.toggle('active', b.textContent.toLowerCase().includes(name) || b.getAttribute('onclick') === `showTab('${name}')`);
  });
  document.querySelectorAll('.tab-panel').forEach(p => {
    p.classList.toggle('active', p.id === `tab-${name}`);
  });

  if (name === 'media') {
    // Open all media sections by default
    document.querySelectorAll('[id^="media-sec-body-"]').forEach(b => b.classList.add('open'));
  }
  if (name === 'log') {
    const lb  = document.getElementById('log-box');
    if (lb) apiFetch(`api/get_log.php?project_id=${_currentProjectId}`, null, 'GET')
              .then(d => { lb.textContent = d.log || '(no log)'; lb.scrollTop = lb.scrollHeight; });
  }
}

// ---------------------------------------------------------------------------
// Close modal on overlay click
// ---------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
  const overlay = document.getElementById('project-modal');
  if (overlay) {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) closeModal();
    });
  }
});
