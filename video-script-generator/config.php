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
    <h2>⚙️ API Configuration</h2>
    <p class="text-muted">Set up your API keys to enable all features.</p>
  </div>

  <div id="save-alert" class="alert" style="display:none"></div>

  <form id="config-form" class="config-grid">

    <!-- Claude -->
    <div class="config-card">
      <div class="config-card-header">
        <span class="provider-icon claude-icon">C</span>
        <div>
          <h3>Anthropic Claude</h3>
          <p>Script generation &amp; media search</p>
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
          <p>Video timestamp analysis &amp; alt. generation</p>
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
          <p>Free stock images &amp; videos (B-ROLL)</p>
        </div>
        <div class="badge badge-required">Required</div>
      </div>
      <div class="form-row">
        <label>API Key</label>
        <div class="input-with-toggle">
          <input type="password" id="pexels_api_key" name="pexels_api_key" placeholder="Your Pexels API key" autocomplete="off">
          <button type="button" class="toggle-visibility" data-target="pexels_api_key">👁</button>
        </div>
        <small>Free key at <a href="https://www.pexels.com/api/" target="_blank">pexels.com/api</a></small>
      </div>
      <button type="button" class="btn btn-sm btn-outline" onclick="testApi('pexels')">Test Connection</button>
      <span id="pexels-test-result" class="test-result"></span>
    </div>

    <!-- Google TTS -->
    <div class="config-card">
      <div class="config-card-header">
        <span class="provider-icon google-icon">🔊</span>
        <div>
          <h3>Google Cloud TTS</h3>
          <p>Natural text-to-speech narration</p>
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
          <p>Ultra-realistic AI voices</p>
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

    <!-- System -->
    <div class="config-card">
      <div class="config-card-header">
        <span class="provider-icon sys-icon">⚙</span>
        <div>
          <h3>System</h3>
          <p>Binary paths for processing</p>
        </div>
      </div>
      <div class="form-row">
        <label>Python binary</label>
        <input type="text" id="python_bin" name="python_bin" value="python3">
      </div>
      <div class="form-row">
        <label>FFmpeg binary</label>
        <input type="text" id="ffmpeg_bin" name="ffmpeg_bin" value="ffmpeg">
      </div>
    </div>

    <div class="config-actions">
      <button type="submit" class="btn btn-primary btn-lg">💾 Save All Settings</button>
    </div>
  </form>
</main>

<script src="assets/js/app.js"></script>
<script>
// Load current values
fetch('api/get_config.php').then(r=>r.json()).then(cfg => {
  const masked = ['claude_api_key','gemini_api_key','pexels_api_key','google_tts_api_key','elevenlabs_api_key'];
  Object.keys(cfg).forEach(k => {
    const el = document.getElementById(k);
    if (!el) return;
    el.value = masked.includes(k) && cfg[k] ? '••••••••••••••••' : cfg[k];
    el.dataset.original = cfg[k];
  });
});

// Don't send masked placeholder
document.querySelectorAll('.input-with-toggle input').forEach(inp => {
  inp.addEventListener('focus', () => {
    if (inp.value === '••••••••••••••••') inp.value = '';
  });
  inp.addEventListener('blur', () => {
    if (!inp.value && inp.dataset.original) inp.value = '••••••••••••••••';
  });
});

document.querySelectorAll('.toggle-visibility').forEach(btn => {
  btn.addEventListener('click', () => {
    const inp = document.getElementById(btn.dataset.target);
    inp.type = inp.type === 'password' ? 'text' : 'password';
  });
});

document.getElementById('config-form').addEventListener('submit', async e => {
  e.preventDefault();
  const fd  = new FormData(e.target);
  const obj = {};
  fd.forEach((v,k) => { if (v && v !== '••••••••••••••••') obj[k] = v; });
  const res = await fetch('api/save_config.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(obj)
  });
  const data = await res.json();
  const alert = document.getElementById('save-alert');
  alert.className = 'alert ' + (data.success ? 'alert-success' : 'alert-error');
  alert.textContent = data.message || (data.error ?? 'Error');
  alert.style.display = 'block';
  setTimeout(() => alert.style.display = 'none', 4000);
});

async function testApi(provider) {
  const res   = await fetch(`api/test_connection.php?provider=${provider}`);
  const data  = await res.json();
  const el    = document.getElementById(`${provider}-test-result`);
  el.textContent = data.success ? '✅ Connected' : '❌ ' + data.error;
  el.className   = 'test-result ' + (data.success ? 'ok' : 'fail');
}
</script>
</body>
</html>
