<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$activePage = 'knowledge';
$pageTitle  = 'Base de Conhecimento';
$kb         = dbFetchOne($db, 'SELECT * FROM knowledge_base LIMIT 1');

include __DIR__ . '/../includes/layout-admin.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">📚 Base de Conhecimento</div>
    <div class="page-subtitle">Informações que o bot usará para responder os clientes</div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Fonte de Conhecimento</div>
    <div class="flex gap-2">
      <button onclick="selectSource('text')" id="btn-text" class="btn btn-outline btn-sm">📝 Texto</button>
      <button onclick="selectSource('url')" id="btn-url" class="btn btn-outline btn-sm">🌐 URL</button>
    </div>
  </div>
  <div class="card-body">

    <!-- TEXTO -->
    <div id="source-text">
      <div class="form-group">
        <label class="form-label">Conteúdo Manual</label>
        <textarea id="kb-content" class="form-control" rows="18" placeholder="Escreva aqui todas as informações sobre seu negócio:
- Nome do estabelecimento
- Endereço e horários
- Produtos/serviços oferecidos
- Preços
- Formas de pagamento
- Políticas de entrega
- FAQ (perguntas frequentes)
- Qualquer outra informação relevante..."><?= htmlspecialchars($kb['source_type'] === 'text' ? ($kb['content'] ?? '') : '') ?></textarea>
        <div class="form-hint">Quanto mais detalhado, melhor o bot vai responder. Inclua FAQs e informações do negócio.</div>
      </div>
      <button onclick="saveKnowledge('text')" class="btn btn-primary">
        <i class="fa-solid fa-floppy-disk"></i> Salvar Base de Conhecimento
      </button>
    </div>

    <!-- URL -->
    <div id="source-url" style="display:none">
      <div class="alert alert-info">
        💡 O sistema irá extrair automaticamente o conteúdo da URL informada e usar como base de conhecimento.
        Sempre que você salvar uma nova URL, o conteúdo anterior (inclusive texto manual) será substituído.
      </div>
      <div class="form-group">
        <label class="form-label">URL do Site / Página</label>
        <input type="url" id="kb-url" class="form-control" placeholder="https://seusite.com.br/sobre"
               value="<?= htmlspecialchars($kb['url'] ?? '') ?>">
        <div class="form-hint">
          Ex: página "sobre nós", cardápio, lista de serviços, etc.
        </div>
      </div>

      <?php if ($kb && $kb['source_type'] === 'url' && $kb['url_content']): ?>
      <div class="form-group">
        <label class="form-label">Prévia do conteúdo extraído
          <small>(atualizado em <?= $kb['last_url_fetch'] ? date('d/m/Y H:i', strtotime($kb['last_url_fetch'])) : '—' ?>)</small>
        </label>
        <textarea class="form-control" rows="8" readonly style="background:var(--bg-main);font-size:12px"><?= htmlspecialchars(substr($kb['url_content'], 0, 2000)) ?>...</textarea>
      </div>
      <?php endif; ?>

      <div class="flex gap-2">
        <button onclick="saveKnowledge('url')" class="btn btn-primary" id="btn-fetch">
          <i class="fa-solid fa-download"></i> Importar Conteúdo da URL
        </button>
        <?php if ($kb && $kb['url'] && $kb['source_type'] === 'url'): ?>
        <button onclick="saveKnowledge('url')" class="btn btn-outline">
          <i class="fa-solid fa-rotate"></i> Atualizar
        </button>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script>
const currentSource = '<?= htmlspecialchars($kb['source_type'] ?? 'text') ?>';
selectSource(currentSource);

function selectSource(type) {
  document.getElementById('source-text').style.display = type === 'text' ? 'block' : 'none';
  document.getElementById('source-url').style.display  = type === 'url'  ? 'block' : 'none';
  document.getElementById('btn-text').className = 'btn btn-sm ' + (type === 'text' ? 'btn-primary' : 'btn-outline');
  document.getElementById('btn-url').className  = 'btn btn-sm ' + (type === 'url'  ? 'btn-primary' : 'btn-outline');
}

async function saveKnowledge(type) {
  const btn = document.getElementById(type === 'url' ? 'btn-fetch' : null) || document.querySelector('[onclick*="saveKnowledge"]');
  const data = { source_type: type };

  if (type === 'text') {
    const content = document.getElementById('kb-content').value.trim();
    if (!content) { toast('Escreva algum conteúdo antes de salvar', 'error'); return; }
    data.content = content;
  } else {
    const url = document.getElementById('kb-url').value.trim();
    if (!url) { toast('Informe uma URL válida', 'error'); return; }
    data.url = url;
  }

  if (type === 'url') {
    const btn2 = document.getElementById('btn-fetch');
    if (btn2) { btn2.disabled = true; btn2.innerHTML = '<span class="spinner"></span> Importando...'; }
  }

  const res = await api('/api/knowledge.php', 'POST', data);

  if (type === 'url') {
    const btn2 = document.getElementById('btn-fetch');
    if (btn2) { btn2.disabled = false; btn2.innerHTML = '<i class="fa-solid fa-download"></i> Importar Conteúdo da URL'; }
  }

  if (res.success) {
    toast(res.message || 'Base de conhecimento atualizada!', 'success');
    if (res.preview) setTimeout(() => location.reload(), 1500);
  } else {
    toast(res.error || 'Erro ao salvar', 'error');
  }
}
</script>

<?php include __DIR__ . '/../includes/layout-admin-end.php'; ?>
