<?php require_once 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Video Script Generator — AI Powered</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include '_header.php'; ?>

<main class="container">

  <!-- Hero / New Project -->
  <section class="hero-section">
    <h1>AI Video Script Generator</h1>
    <p class="hero-sub">Generate complete video scripts with B-ROLL, voiceover &amp; media — ready to import into your editor.</p>

    <div class="new-project-card" id="new-project-form">
      <h3>✨ Create New Project</h3>
      <div class="form-grid-2">
        <div class="form-row">
          <label>Video Theme / Topic</label>
          <input type="text" id="np-theme" placeholder="e.g. The Future of Artificial Intelligence" required>
        </div>
        <div class="form-row">
          <label>Video Duration</label>
          <select id="np-duration">
            <option value="2">~2 minutes</option>
            <option value="3">~3 minutes</option>
            <option value="5" selected>~5 minutes</option>
            <option value="7">~7 minutes</option>
            <option value="10">~10 minutes</option>
            <option value="15">~15 minutes</option>
          </select>
        </div>
        <div class="form-row">
          <label>AI Provider</label>
          <select id="np-ai">
            <option value="claude">Claude (Anthropic)</option>
            <option value="gemini">Gemini (Google)</option>
            <option value="both">Both — Claude + Gemini</option>
          </select>
        </div>
        <div class="form-row">
          <label>Voice Provider</label>
          <select id="np-voice-provider" onchange="updateVoiceList()">
            <option value="google">Google TTS</option>
            <option value="elevenlabs">ElevenLabs</option>
          </select>
        </div>
        <div class="form-row">
          <label>Voice</label>
          <select id="np-voice-id"></select>
        </div>
        <div class="form-row">
          <label>Language</label>
          <select id="np-language">
            <option value="en-US">English (US)</option>
            <option value="en-GB">English (UK)</option>
            <option value="pt-BR">Portuguese (Brazil)</option>
            <option value="pt-PT">Portuguese (Portugal)</option>
            <option value="es-ES">Spanish (Spain)</option>
            <option value="es-MX">Spanish (Mexico)</option>
            <option value="fr-FR">French</option>
            <option value="de-DE">German</option>
            <option value="it-IT">Italian</option>
            <option value="ja-JP">Japanese</option>
          </select>
        </div>
      </div>
      <div id="np-error" class="alert alert-error" style="display:none"></div>
      <button class="btn btn-primary btn-lg" onclick="createProject()">
        <span id="create-btn-text">🚀 Generate Script</span>
        <span id="create-btn-spinner" class="spinner" style="display:none"></span>
      </button>
    </div>
  </section>

  <!-- Projects List -->
  <section class="projects-section">
    <div class="section-header">
      <h2>Your Projects</h2>
      <button class="btn btn-sm btn-outline" onclick="loadProjects()">↻ Refresh</button>
    </div>
    <div id="projects-list" class="projects-grid">
      <div class="loading-placeholder">Loading projects...</div>
    </div>
  </section>

</main>

<!-- Project Detail Modal -->
<div id="project-modal" class="modal-overlay" style="display:none">
  <div class="modal-box modal-large">
    <button class="modal-close" onclick="closeModal()">✕</button>
    <div id="modal-content"></div>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
const GOOGLE_VOICES = {
  'en-US': [
    {id:'en-US-Neural2-F', label:'Neural2-F (Female, Natural)'},
    {id:'en-US-Neural2-D', label:'Neural2-D (Male, Natural)'},
    {id:'en-US-Studio-O',  label:'Studio-O (Female, Premium)'},
    {id:'en-US-Studio-Q',  label:'Studio-Q (Male, Premium)'},
    {id:'en-US-Wavenet-A', label:'WaveNet-A (Female)'},
    {id:'en-US-Wavenet-B', label:'WaveNet-B (Male)'},
  ],
  'en-GB': [
    {id:'en-GB-Neural2-A', label:'Neural2-A (Female)'},
    {id:'en-GB-Neural2-B', label:'Neural2-B (Male)'},
    {id:'en-GB-Studio-B',  label:'Studio-B (Male, Premium)'},
    {id:'en-GB-Studio-C',  label:'Studio-C (Female, Premium)'},
  ],
  'pt-BR': [
    {id:'pt-BR-Neural2-A', label:'Neural2-A (Female)'},
    {id:'pt-BR-Neural2-B', label:'Neural2-B (Male)'},
    {id:'pt-BR-Studio-B',  label:'Studio-B (Male, Premium)'},
    {id:'pt-BR-Studio-C',  label:'Studio-C (Female, Premium)'},
    {id:'pt-BR-Wavenet-A', label:'WaveNet-A (Female)'},
    {id:'pt-BR-Wavenet-B', label:'WaveNet-B (Male)'},
  ],
  'pt-PT': [
    {id:'pt-PT-Neural2-A', label:'Neural2-A (Female)'},
    {id:'pt-PT-Neural2-B', label:'Neural2-B (Male)'},
    {id:'pt-PT-Wavenet-A', label:'WaveNet-A (Female)'},
  ],
  'es-ES': [
    {id:'es-ES-Neural2-A', label:'Neural2-A (Female)'},
    {id:'es-ES-Neural2-B', label:'Neural2-B (Male)'},
    {id:'es-ES-Studio-C',  label:'Studio-C (Female, Premium)'},
  ],
};

function updateVoiceList() {
  const provider  = document.getElementById('np-voice-provider').value;
  const lang      = document.getElementById('np-language').value;
  const sel       = document.getElementById('np-voice-id');
  sel.innerHTML   = '';

  if (provider === 'elevenlabs') {
    const voices = [
      {id:'21m00Tcm4TlvDq8ikWAM', label:'Rachel (Female, calm)'},
      {id:'AZnzlk1XvdvUeBnXmlld', label:'Domi (Female, strong)'},
      {id:'EXAVITQu4vr4xnSDxMaL', label:'Bella (Female, soft)'},
      {id:'ErXwobaYiN019PkySvjV', label:'Antoni (Male, well-rounded)'},
      {id:'MF3mGyEYCl7XYWbV9V6O', label:'Elli (Female, emotional)'},
      {id:'TxGEqnHWrfWFTfGW9XjX', label:'Josh (Male, deep)'},
      {id:'VR6AewLTigWG4xSOukaG', label:'Arnold (Male, crisp)'},
      {id:'pNInz6obpgDQGcFmaJgB', label:'Adam (Male, deep)'},
      {id:'yoZ06aMxZJJ28mfd3POQ', label:'Sam (Male, raspy)'},
    ];
    voices.forEach(v => {
      const o  = document.createElement('option');
      o.value  = v.id;
      o.text   = v.label;
      sel.add(o);
    });
    sel.insertAdjacentHTML('afterbegin', '<option value="">— Enter custom Voice ID —</option>');
  } else {
    const voices = GOOGLE_VOICES[lang] || GOOGLE_VOICES['en-US'];
    voices.forEach(v => {
      const o  = document.createElement('option');
      o.value  = v.id;
      o.text   = v.label;
      sel.add(o);
    });
  }
}

document.getElementById('np-language').addEventListener('change', updateVoiceList);
updateVoiceList();

async function createProject() {
  const theme    = document.getElementById('np-theme').value.trim();
  const duration = parseInt(document.getElementById('np-duration').value);
  const ai       = document.getElementById('np-ai').value;
  const voicePrv = document.getElementById('np-voice-provider').value;
  const voiceId  = document.getElementById('np-voice-id').value;
  const lang     = document.getElementById('np-language').value;

  if (!theme) { showError('np-error', 'Please enter a video theme.'); return; }

  setBusy(true);
  try {
    const res  = await apiFetch('api/create_project.php', {theme, duration_minutes: duration, ai_provider: ai, voice_provider: voicePrv, voice_id: voiceId, language_code: lang});
    if (res.error) { showError('np-error', res.error); return; }
    openProject(res.project_id);
    loadProjects();
  } finally { setBusy(false); }
}

function setBusy(on) {
  document.getElementById('create-btn-text').style.display    = on ? 'none' : '';
  document.getElementById('create-btn-spinner').style.display = on ? 'inline-block' : 'none';
}

loadProjects();
</script>
</body>
</html>
