<?php require_once 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Configuration — Video Script Generator</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include '_header.php'; ?>

<main class="container">
  <div class="page-header">
    <h2>⚙️ Configuration</h2>
    <p class="text-muted">Configure API keys, voices, and your local Python worker connection.</p>
  </div>

  <div id="save-alert" class="alert" style="display:none"></div>

  <form id="config-form" class="config-grid">

    <!-- Server -->
    <div class="config-card config-card-full">
      <div class="config-card-header">
        <span class="provider-icon sys-icon">🌐</span>
        <div>
          <h3>Server Settings</h3>
          <p>Public URL of this PHP server — used by the Python worker to upload files</p>
        </div>
        <div class="badge badge-required">Required</div>
      </div>
      <div class="form-row">
        <label>Server Public URL</label>
        <input type="text" id="server_public_url" name="server_public_url"
               placeholder="https://yourserver.com/video-script-generator">
        <small>No trailing slash. Example: <code>https://mysite.com/videoscript</code></small>
      </div>
    </div>

    <!-- Claude -->
    <div class="config-card">
      <div class="config-card-header">
        <span class="provider-icon claude-icon">C</span>
        <div>
          <h3>Anthropic Claude</h3>
          <p>Script generation — runs on server</p>
        </div>
        <div class="badge badge-recommended">Recommended</div>
      </div>
      <div class="form-row">
        <label>API Key</label>
        <div class="input-with-toggle">
          <input type="password" id="claude_api_key" name="claude_api_key" placeholder="sk-ant-..." autocomplete="off">
          <button type="button" class="toggle-visibility" data-target="claude_api_key">👁</button>
        </div>
      </div>
      <div class="form-row">
        <label>Default Model</label>
        <select id="claude_model" name="claude_model">
          <option value="claude-opus-4-7">Claude Opus 4.7 (Best quality)</option>
          <option value="claude-sonnet-4-6">Claude Sonnet 4.6 (Fast)</option>
          <option value="claude-haiku-4-5-20251001">Claude Haiku 4.5 (Fastest)</option>
        </select>
      </div>
      <button type="button" class="btn btn-sm btn-outline" onclick="testApi('claude')">Test Connection</button>
      <span id="claude-test-result" class="test-result"></span>
    </div>

    <!-- Gemini -->
    <div class="config-card">
      <div class="config-card-header">
        <span class="provider-icon gemini-icon">G</span>
        <div>
          <h3>Google Gemini</h3>
          <p>Video timestamp analysis — used by Python worker</p>
        </div>
      </div>
      <div class="form-row">
        <label>API Key</label>
        <div class="input-with-toggle">
          <input type="password" id="gemini_api_key" name="gemini_api_key" placeholder="AIza..." autocomplete="off">
          <button type="button" class="toggle-visibility" data-target="gemini_api_key">👁</button>
        </div>
      </div>
      <button type="button" class="btn btn-sm btn-outline" onclick="testApi('gemini')">Test Connection</button>
      <span id="gemini-test-result" class="test-result"></span>
    </div>

    <!-- Pexels -->
    <div class="config-card">
      <div class="config-card-header">
        <span class="provider-icon pexels-icon">P</span>
        <div>
          <h3>Pexels</h3>
          <p>Free stock images &amp; videos (B-ROLL) — runs on server</p>
        </div>
      </div>
      <div class="form-row">
        <label>API Key</label>
        <div class="input-with-toggle">
          <input type="password" id="pexels_api_key" name="pexels_api_key" placeholder="Your Pexels API key" autocomplete="off">
          <button type="button" class="toggle-visibility" data-target="pexels_api_key">👁</button>
        </div>
        <small>Free key at <a href="https://www.pexels.com/api/" target="_blank">pexels.com/api</a> — good for nature, people, business</small>
      </div>
      <button type="button" class="btn btn-sm btn-outline" onclick="testApi('pexels')">Test Connection</button>
      <span id="pexels-test-result" class="test-result"></span>
    </div>

    <!-- YouTube -->
    <div class="config-card">
      <div class="config-card-header">
        <span class="provider-icon yt-icon">▶</span>
        <div>
          <h3>YouTube Data API v3</h3>
          <p>Search YouTube for B-ROLL — films, Bible, documentaries, music</p>
        </div>
      </div>
      <div class="form-row">
        <label>API Key</label>
        <div class="input-with-toggle">
          <input type="password" id="youtube_api_key" name="youtube_api_key" placeholder="AIza..." autocomplete="off">
          <button type="button" class="toggle-visibility" data-target="youtube_api_key">👁</button>
        </div>
        <small>Free key at <a href="https://console.cloud.google.com/" target="_blank">console.cloud.google.com</a> → Enable "YouTube Data API v3"</small>
      </div>
      <div class="yt-notice">
        ⚠️ YouTube videos may be copyrighted. Download is for personal editing only.
        The Python worker uses <code>yt-dlp</code> to download selected videos locally.
      </div>
      <button type="button" class="btn btn-sm btn-outline" onclick="testApi('youtube')">Test Connection</button>
      <span id="youtube-test-result" class="test-result"></span>
    </div>

    <!-- Google TTS -->
    <div class="config-card">
      <div class="config-card-header">
        <span class="provider-icon google-icon">🔊</span>
        <div>
          <h3>Google Cloud TTS</h3>
          <p>Natural narration — used by Python worker</p>
        </div>
      </div>
      <div class="form-row">
        <label>API Key</label>
        <div class="input-with-toggle">
          <input type="password" id="google_tts_api_key" name="google_tts_api_key" placeholder="AIza..." autocomplete="off">
          <button type="button" class="toggle-visibility" data-target="google_tts_api_key">👁</button>
        </div>
      </div>
    </div>

    <!-- ElevenLabs -->
    <div class="config-card">
      <div class="config-card-header">
        <span class="provider-icon eleven-icon">11</span>
        <div>
          <h3>ElevenLabs</h3>
          <p>Ultra-realistic AI voices — used by Python worker</p>
        </div>
      </div>
      <div class="form-row">
        <label>API Key</label>
        <div class="input-with-toggle">
          <input type="password" id="elevenlabs_api_key" name="elevenlabs_api_key" placeholder="Your ElevenLabs key" autocomplete="off">
          <button type="button" class="toggle-visibility" data-target="elevenlabs_api_key">👁</button>
        </div>
      </div>
    </div>

    <!-- FFmpeg path (server-side, for future use) -->
    <div class="config-card">
      <div class="config-card-header">
        <span class="provider-icon sys-icon">⚙</span>
        <div>
          <h3>Server System</h3>
          <p>FFmpeg path on the server (used for thumbnails)</p>
        </div>
      </div>
      <div class="form-row">
        <label>FFmpeg binary</label>
        <input type="text" id="ffmpeg_bin" name="ffmpeg_bin" value="ffmpeg">
        <small>Python worker uses its own local FFmpeg — configured in <code>worker_config.json</code></small>
      </div>
    </div>

    <div class="config-actions">
      <button type="submit" class="btn btn-primary btn-lg">💾 Save All Settings</button>
    </div>
  </form>

  <!-- ================================================================
       Worker Setup Section
       ================================================================ -->
  <hr class="divider" style="margin: 32px 0">

  <div class="page-header" style="padding-top:0">
    <h2>🐍 Python Worker Setup</h2>
    <p class="text-muted">
      The Python worker runs on <strong>your local machine</strong> and communicates with this server
      via a secure token. It handles video downloading, cutting, and TTS generation.
    </p>
  </div>

  <div class="worker-setup-grid">

    <!-- Token card -->
    <div class="config-card">
      <div class="config-card-header">
        <span class="provider-icon sys-icon">🔑</span>
        <div>
          <h3>Worker API Token</h3>
          <p>Secret key that authenticates your local Python worker</p>
        </div>
      </div>
      <div class="form-row">
        <label>Current Token</label>
        <div class="input-with-toggle">
          <input type="password" id="worker_token_display" readonly placeholder="(not generated yet)" style="font-family:monospace;font-size:.85rem">
          <button type="button" class="toggle-visibility" data-target="worker_token_display">👁</button>
          <button type="button" class="btn btn-sm btn-outline" onclick="copyToken()" id="copy-btn" style="display:none">📋</button>
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:4px">
        <button class="btn btn-sm btn-primary" onclick="generateToken()">⚡ Generate New Token</button>
        <span id="token-status" class="test-result"></span>
      </div>
      <p class="text-muted" style="font-size:.8rem;margin-top:12px">
        ⚠️ Regenerating invalidates the old token — update <code>worker_config.json</code> on your machine.
      </p>
    </div>

    <!-- Instructions card -->
    <div class="config-card">
      <div class="config-card-header">
        <span class="provider-icon sys-icon">📋</span>
        <div>
          <h3>Local Setup Instructions</h3>
          <p>Run these commands on your machine</p>
        </div>
      </div>
      <div class="setup-steps">
        <div class="setup-step">
          <span class="step-num">1</span>
          <div>
            <strong>Install Python dependencies</strong>
            <pre class="code-block">pip install -r requirements.txt</pre>
          </div>
        </div>
        <div class="setup-step">
          <span class="step-num">2</span>
          <div>
            <strong>Create config file</strong>
            <pre class="code-block" id="config-snippet">Copy the snippet below after generating a token →</pre>
          </div>
        </div>
        <div class="setup-step">
          <span class="step-num">3</span>
          <div>
            <strong>Run the worker</strong>
            <pre class="code-block"># Watch for queued projects automatically:
python3 worker.py

# Or process a specific project:
python3 worker.py --project-id 42</pre>
          </div>
        </div>
      </div>
    </div>

  </div>

</main>

<style>
.config-card-full { grid-column: 1 / -1; }
.worker-setup-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  padding-bottom: 40px;
}
@media(max-width:768px){ .worker-setup-grid { grid-template-columns: 1fr; } }

.setup-steps { display: flex; flex-direction: column; gap: 16px; }
.setup-step  { display: flex; gap: 14px; align-items: flex-start; }
.step-num {
  width: 26px; height: 26px;
  background: var(--accent);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: .8rem; font-weight: 700; flex-shrink: 0; margin-top: 2px;
}
.code-block {
  background: #0a0a0a;
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 10px 14px;
  font-family: 'Fira Code','Courier New',monospace;
  font-size: .78rem;
  color: #7ec8a0;
  white-space: pre-wrap;
  word-break: break-all;
  margin-top: 6px;
}
</style>

<script src="assets/js/app.js"></script>
<script>
// -----------------------------------------------------------------------
// Load current config
// -----------------------------------------------------------------------
fetch('api/get_config.php').then(r=>r.json()).then(cfg => {
  const masked = ['claude_api_key','gemini_api_key','pexels_api_key','google_tts_api_key','elevenlabs_api_key'];
  Object.keys(cfg).forEach(k => {
    const el = document.getElementById(k);
    if (!el) return;
    el.value = (masked.includes(k) && cfg[k]) ? '••••••••••••••••' : (cfg[k] || '');
    el.dataset.original = cfg[k] || '';
  });
  // Worker token
  fetch('api/worker/get_token_status.php').then(r=>r.json()).then(t => {
    if (t.configured) {
      document.getElementById('worker_token_display').value = '••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••';
      document.getElementById('worker_token_display').dataset.token = '';
      document.getElementById('copy-btn').style.display = 'inline-flex';
      updateConfigSnippet('(token configured — generate to reveal)');
    }
  }).catch(()=>{});
});

// Don't send masked placeholder
document.querySelectorAll('.input-with-toggle input[type=password]').forEach(inp => {
  inp.addEventListener('focus', () => { if (inp.value.startsWith('••')) inp.value = ''; });
  inp.addEventListener('blur',  () => { if (!inp.value && inp.dataset.original) inp.value = '••••••••••••••••'; });
});

document.querySelectorAll('.toggle-visibility').forEach(btn => {
  btn.addEventListener('click', () => {
    const inp = document.getElementById(btn.dataset.target);
    if (!inp) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
  });
});

// -----------------------------------------------------------------------
// Save config
// -----------------------------------------------------------------------
document.getElementById('config-form').addEventListener('submit', async e => {
  e.preventDefault();
  const fd  = new FormData(e.target);
  const obj = {};
  fd.forEach((v,k) => { if (v && !v.startsWith('••')) obj[k] = v; });
  const res  = await fetch('api/save_config.php', {
    method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(obj)
  });
  const data = await res.json();
  const alert = document.getElementById('save-alert');
  alert.className   = 'alert ' + (data.success ? 'alert-success' : 'alert-error');
  alert.textContent = data.message || data.error || 'Error';
  alert.style.display = 'block';
  setTimeout(() => alert.style.display = 'none', 4000);
  // Update snippet if server URL was saved
  if (obj.server_public_url) updateConfigSnippet(document.getElementById('worker_token_display').dataset.token || 'YOUR_TOKEN_HERE', obj.server_public_url);
});

// -----------------------------------------------------------------------
// Test API connections
// -----------------------------------------------------------------------
async function testApi(provider) {
  const res  = await fetch(`api/test_connection.php?provider=${provider}`);
  const data = await res.json();
  const el   = document.getElementById(`${provider}-test-result`);
  el.textContent = data.success ? '✅ Connected' : '❌ ' + data.error;
  el.className   = 'test-result ' + (data.success ? 'ok' : 'fail');
}

// -----------------------------------------------------------------------
// Worker token
// -----------------------------------------------------------------------
async function generateToken() {
  const res  = await fetch('api/worker/generate_token.php', { method: 'POST' });
  const data = await res.json();
  if (!data.success) { alert('Error: ' + data.error); return; }

  const inp   = document.getElementById('worker_token_display');
  inp.value   = data.token;
  inp.type    = 'text';
  inp.dataset.token = data.token;

  document.getElementById('copy-btn').style.display = 'inline-flex';
  const el = document.getElementById('token-status');
  el.textContent = '✅ Token generated!';
  el.className   = 'test-result ok';
  setTimeout(() => el.textContent = '', 4000);

  const serverUrl = document.getElementById('server_public_url').value.trim()
                  || 'https://yourserver.com/video-script-generator';
  updateConfigSnippet(data.token, serverUrl);
}

function copyToken() {
  const token = document.getElementById('worker_token_display').dataset.token
             || document.getElementById('worker_token_display').value;
  navigator.clipboard.writeText(token);
  const btn = document.getElementById('copy-btn');
  btn.textContent = '✅';
  setTimeout(() => btn.textContent = '📋', 2000);
}

function updateConfigSnippet(token, serverUrl) {
  serverUrl = serverUrl || document.getElementById('server_public_url').value.trim()
                        || 'https://yourserver.com/video-script-generator';
  const snippet = `{
  "server_url":     "${serverUrl}",
  "api_token":      "${token}",
  "local_work_dir": "/tmp/videoscript_work",
  "ffmpeg_bin":     "ffmpeg"
}`;
  document.getElementById('config-snippet').textContent = snippet;
}
</script>
</body>
</html>
