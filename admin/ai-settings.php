<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$activePage = 'ai-settings';
$pageTitle  = 'Configurações de IA';
$config     = getConfig($db);
$aiKeys     = dbFetchAll($db, 'SELECT * FROM ai_keys');
$keysByProv = [];
foreach ($aiKeys as $k) { $keysByProv[$k['provider']] = $k; }

include __DIR__ . '/../includes/layout-admin.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">🧠 Configurações de IA</div>
    <div class="page-subtitle">Gerencie as chaves de API e defina qual IA usar</div>
  </div>
</div>

<!-- Seleção de IA -->
<div class="card mb-3">
  <div class="card-header"><div class="card-title">🔀 Modo de Uso das IAs</div></div>
  <div class="card-body">
    <div class="grid-3" style="gap:16px">
      <?php
      $opts = [
        'claude'     => ['icon' => '🟣', 'name' => 'Apenas Claude',  'desc' => 'Usa somente a API da Anthropic'],
        'gemini'     => ['icon' => '🔵', 'name' => 'Apenas Gemini',  'desc' => 'Usa somente a API do Google'],
        'gpt'        => ['icon' => '🟢', 'name' => 'Apenas GPT',     'desc' => 'Usa somente a API da OpenAI'],
        'sequential' => ['icon' => '🔄', 'name' => 'Sequencial',     'desc' => 'Troca automaticamente ao esgotar créditos'],
      ];
      foreach ($opts as $k => $v):
      ?>
      <label style="cursor:pointer" class="card" onclick="selectAI('<?= $k ?>')">
        <div class="card-body" style="padding:16px;text-align:center" id="ai-option-<?= $k ?>">
          <div style="font-size:28px;margin-bottom:8px"><?= $v['icon'] ?></div>
          <div style="font-weight:700;font-size:14px"><?= $v['name'] ?></div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:4px"><?= $v['desc'] ?></div>
        </div>
      </label>
      <?php endforeach; ?>
    </div>
    <input type="hidden" id="active-ai-value" value="<?= htmlspecialchars($config['active_ai'] ?? 'claude') ?>">

    <div id="sequential-order" style="display:none;margin-top:20px">
      <label class="form-label">Sequência de Fallback (arraste para reordenar)</label>
      <div id="sequence-list" style="display:flex;gap:10px;flex-wrap:wrap">
        <?php
        $seq = json_decode($config['ai_sequence'] ?? '["claude","gemini","gpt"]', true) ?: ['claude','gemini','gpt'];
        $seqNames = ['claude' => '🟣 Claude', 'gemini' => '🔵 Gemini', 'gpt' => '🟢 GPT'];
        foreach ($seq as $i => $p):
        ?>
        <div class="sequence-item" data-provider="<?= $p ?>" draggable="true"
             style="padding:10px 18px;background:var(--bg-main);border:1.5px solid var(--border);border-radius:8px;cursor:grab;font-weight:600;font-size:14px;display:flex;align-items:center;gap:8px">
          <span style="color:var(--text-muted);font-size:12px"><?= $i+1 ?>.</span>
          <?= $seqNames[$p] ?? $p ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="form-hint mt-1">Quando os créditos de uma IA acabam, o bot usa a próxima automaticamente.</div>
      <button onclick="saveAIMode()" class="btn btn-primary btn-sm mt-2">Salvar Sequência</button>
    </div>
    <div id="single-ai-save" style="margin-top:16px">
      <button onclick="saveAIMode()" class="btn btn-primary">Salvar Configuração de IA</button>
    </div>
  </div>
</div>

<!-- Chaves de API -->
<div class="grid-3" style="gap:20px;align-items:start">
  <?php
  $providers = [
    'claude' => ['name' => 'Claude (Anthropic)', 'color' => '#7c3aed', 'bg' => '#ede9fe', 'icon' => '🟣',
                 'models' => ['claude-3-5-haiku-20241022','claude-3-5-sonnet-20241022','claude-opus-4-7','claude-sonnet-4-6'],
                 'link' => 'https://console.anthropic.com/'],
    'gemini' => ['name' => 'Gemini (Google)', 'color' => '#2563eb', 'bg' => '#dbeafe', 'icon' => '🔵',
                 'models' => ['gemini-1.5-flash','gemini-1.5-pro','gemini-2.0-flash'],
                 'link' => 'https://aistudio.google.com/'],
    'gpt'    => ['name' => 'GPT (OpenAI)', 'color' => '#059669', 'bg' => '#d1fae5', 'icon' => '🟢',
                 'models' => ['gpt-4o-mini','gpt-4o','gpt-4-turbo'],
                 'link' => 'https://platform.openai.com/'],
  ];
  foreach ($providers as $prov => $info):
    $key = $keysByProv[$prov] ?? null;
  ?>
  <div class="card">
    <div class="card-header" style="border-top:3px solid <?= $info['color'] ?>">
      <div class="card-title"><?= $info['icon'] ?> <?= $info['name'] ?></div>
      <?php if ($key): ?>
        <span class="badge <?= $key['is_active'] ? 'badge-success' : 'badge-secondary' ?>">
          <?= $key['is_active'] ? 'Ativa' : 'Inativa' ?>
        </span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if ($key && $key['credits_exhausted']): ?>
        <div class="alert alert-warning" style="margin-bottom:14px;padding:10px 12px;font-size:12px">
          ⚠️ Créditos possivelmente esgotados. <button onclick="resetCredits('<?= $prov ?>')" style="background:none;border:none;color:var(--accent);cursor:pointer;font-size:12px;text-decoration:underline">Resetar</button>
        </div>
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Chave de API</label>
        <div style="position:relative">
          <input type="password" id="key-<?= $prov ?>" class="form-control"
                 placeholder="<?= $prov === 'claude' ? 'sk-ant-...' : ($prov === 'gpt' ? 'sk-...' : 'AIza...') ?>"
                 style="padding-right:42px">
          <?php if ($key): ?>
            <div style="position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:12px;color:var(--text-muted)">
              <?= substr($key['api_key'],0,4) ?>...
            </div>
          <?php endif; ?>
        </div>
        <div class="form-hint">
          <a href="<?= $info['link'] ?>" target="_blank" style="color:var(--accent)">
            Obter chave ↗
          </a>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Modelo</label>
        <select id="model-<?= $prov ?>" class="form-select">
          <?php foreach ($info['models'] as $m): ?>
            <option value="<?= $m ?>" <?= ($key['model'] ?? '') === $m ? 'selected' : '' ?>><?= $m ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-check">
          <input type="checkbox" id="active-<?= $prov ?>" <?= ($key['is_active'] ?? 0) ? 'checked' : '' ?>>
          <span>Habilitada</span>
        </label>
      </div>

      <button onclick="saveKey('<?= $prov ?>')" class="btn btn-primary w-full">
        <i class="fa-solid fa-floppy-disk"></i> Salvar Chave
      </button>

      <?php if ($key): ?>
        <button onclick="deleteKey('<?= $prov ?>')" class="btn btn-outline btn-sm w-full mt-1"
                style="color:var(--danger);border-color:var(--danger)">
          <i class="fa-solid fa-trash"></i> Remover
        </button>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<script>
const activeAI = document.getElementById('active-ai-value');

function selectAI(provider) {
  activeAI.value = provider;
  document.querySelectorAll('[id^="ai-option-"]').forEach(el => {
    el.style.border = '';
    el.style.background = '';
  });
  const el = document.getElementById('ai-option-' + provider);
  if (el) { el.style.border = '2px solid var(--accent)'; el.style.background = 'var(--accent-light)'; }
  document.getElementById('sequential-order').style.display = provider === 'sequential' ? 'block' : 'none';
  document.getElementById('single-ai-save').style.display = provider === 'sequential' ? 'none' : 'block';
}

// Initialize
selectAI('<?= $config['active_ai'] ?? 'claude' ?>');

async function saveAIMode() {
  const provider = activeAI.value;
  const items = [...document.querySelectorAll('.sequence-item')].map(el => el.dataset.provider);
  const res = await api('/api/config.php', 'POST', {
    active_ai: provider,
    ai_sequence: JSON.stringify(items)
  });
  toast(res.message || (res.success ? 'Salvo!' : res.error), res.success ? 'success' : 'error');
}

async function saveKey(provider) {
  const key     = document.getElementById('key-' + provider).value.trim();
  const model   = document.getElementById('model-' + provider).value;
  const active  = document.getElementById('active-' + provider).checked ? 1 : 0;
  if (!key) { toast('Digite a chave de API', 'error'); return; }
  const res = await api('/api/ai-keys.php', 'POST', { provider, api_key: key, model, is_active: active });
  toast(res.message || (res.success ? 'Chave salva!' : res.error), res.success ? 'success' : 'error');
  if (res.success) document.getElementById('key-' + provider).value = '';
}

async function deleteKey(provider) {
  if (!confirm('Remover chave de ' + provider + '?')) return;
  const res = await fetch(`/api/ai-keys.php?provider=${provider}`, { method: 'DELETE' });
  const json = await res.json();
  toast(json.message || (json.success ? 'Removida!' : json.error), json.success ? 'success' : 'error');
  if (json.success) setTimeout(() => location.reload(), 800);
}

async function resetCredits(provider) {
  const res = await api('/api/ai-keys.php', 'POST', { provider, api_key: '_keep', is_active: 1 });
  toast('Status de créditos resetado', 'success');
  setTimeout(() => location.reload(), 800);
}

// Drag to reorder sequence
let dragging = null;
document.querySelectorAll('.sequence-item').forEach(item => {
  item.addEventListener('dragstart', () => { dragging = item; item.style.opacity = '.5'; });
  item.addEventListener('dragend', () => { dragging = null; item.style.opacity = ''; });
  item.addEventListener('dragover', e => { e.preventDefault(); });
  item.addEventListener('drop', e => {
    e.preventDefault();
    if (dragging && dragging !== item) {
      const list = document.getElementById('sequence-list');
      const items = [...list.children];
      const dragIdx = items.indexOf(dragging);
      const dropIdx = items.indexOf(item);
      if (dragIdx < dropIdx) item.after(dragging);
      else item.before(dragging);
    }
  });
});
</script>

<?php include __DIR__ . '/../includes/layout-admin-end.php'; ?>
