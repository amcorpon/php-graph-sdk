<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireClient();

$activePage = 'knowledge';
$pageTitle  = 'Base de Conhecimento';
$kb         = dbFetchOne($db, 'SELECT * FROM knowledge_base LIMIT 1');
$config     = getConfig($db);

include __DIR__ . '/../includes/layout-client.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">📚 Base de Conhecimento</div>
    <div class="page-subtitle">Informe ao bot tudo sobre o seu negócio</div>
  </div>
</div>

<!-- Configuração do nome do bot (visível ao cliente) -->
<div class="card mb-3">
  <div class="card-header"><div class="card-title">🤖 Nome do Bot</div></div>
  <div class="card-body">
    <div class="grid-2">
      <div class="form-group">
        <label class="form-label">Nome que o bot usará para se identificar</label>
        <input type="text" id="bot-name" class="form-control" value="<?= htmlspecialchars($config['bot_name'] ?? '') ?>" placeholder="Ex: Bia, João, Assistente...">
        <div class="form-hint">O bot nunca se identifica como robô — sempre responde pelo nome cadastrado</div>
      </div>
      <div style="display:flex;align-items:flex-end">
        <button onclick="saveBotName()" class="btn btn-primary">
          <i class="fa-solid fa-floppy-disk"></i> Salvar Nome
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Base de conhecimento -->
<div class="card">
  <div class="card-header">
    <div class="card-title">📖 Contexto do Bot</div>
    <div class="flex gap-2">
      <button onclick="selectSource('text')" id="btn-text" class="btn btn-outline btn-sm">📝 Texto</button>
      <button onclick="selectSource('url')" id="btn-url" class="btn btn-outline btn-sm">🌐 URL</button>
    </div>
  </div>
  <div class="card-body">
    <div class="alert alert-info">
      💡 Quanto mais detalhado for o contexto, melhor o bot vai responder os clientes. Inclua informações sobre produtos, serviços, horários, endereço, formas de pagamento e FAQ.
    </div>

    <!-- Texto -->
    <div id="source-text">
      <div class="form-group">
        <label class="form-label">Conteúdo do Contexto</label>
        <textarea id="kb-content" class="form-control" rows="16"
                  placeholder="Escreva aqui tudo sobre o seu negócio:

Exemplo:
Somos a Pizzaria Bella Napoli, localizada na Rua das Flores, 123 - Centro.

Horário de funcionamento:
- Segunda a sexta: 11h às 22h
- Sábados e domingos: 11h às 23h

Nossos produtos: [liste seus produtos com preços]

Formas de pagamento aceitas: Dinheiro, PIX, Cartão débito e crédito.

Delivery: Entregamos no raio de 5km. Frete a partir de R$ 5,00.

Qualquer dúvida pode perguntar!"><?= htmlspecialchars($kb && $kb['source_type'] === 'text' ? ($kb['content'] ?? '') : '') ?></textarea>
      </div>
      <button onclick="saveKnowledge('text')" class="btn btn-primary">
        <i class="fa-solid fa-floppy-disk"></i> Salvar Contexto
      </button>
    </div>

    <!-- URL -->
    <div id="source-url" style="display:none">
      <div class="alert alert-warning">
        ⚠️ Ao salvar uma URL, o conteúdo do texto manual será substituído. O sistema irá extrair automaticamente o conteúdo da página para treinar o bot.
      </div>
      <div class="form-group">
        <label class="form-label">URL do Site ou Página</label>
        <input type="url" id="kb-url" class="form-control"
               placeholder="https://seunegocio.com.br/sobre"
               value="<?= htmlspecialchars($kb && $kb['source_type'] === 'url' ? ($kb['url'] ?? '') : '') ?>">
        <div class="form-hint">O bot consultará o conteúdo desta página para responder as dúvidas</div>
      </div>

      <?php if ($kb && $kb['source_type'] === 'url' && $kb['url_content']): ?>
      <div class="form-group">
        <label class="form-label">Conteúdo extraído
          <small>(<?= $kb['last_url_fetch'] ? date('d/m/Y H:i', strtotime($kb['last_url_fetch'])) : '—' ?>)</small>
        </label>
        <textarea class="form-control" rows="6" readonly style="background:var(--bg-main);font-size:12px"><?= htmlspecialchars(substr($kb['url_content'],0,1500)) ?>...</textarea>
      </div>
      <?php endif; ?>

      <div class="flex gap-2">
        <button onclick="saveKnowledge('url')" class="btn btn-primary" id="btn-fetch">
          <i class="fa-solid fa-download"></i> Importar URL
        </button>
        <?php if ($kb && $kb['source_type'] === 'url'): ?>
        <button onclick="saveKnowledge('url')" class="btn btn-outline">
          <i class="fa-solid fa-rotate"></i> Atualizar Conteúdo
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
selectSource('<?= htmlspecialchars($kb['source_type'] ?? 'text') ?>');

function selectSource(type) {
  document.getElementById('source-text').style.display = type === 'text' ? 'block' : 'none';
  document.getElementById('source-url').style.display  = type === 'url'  ? 'block' : 'none';
  document.getElementById('btn-text').className = 'btn btn-sm ' + (type === 'text' ? 'btn-primary' : 'btn-outline');
  document.getElementById('btn-url').className  = 'btn btn-sm ' + (type === 'url'  ? 'btn-primary' : 'btn-outline');
}

async function saveBotName() {
  const name = document.getElementById('bot-name').value.trim();
  if (!name) { toast('Nome não pode ser vazio', 'error'); return; }
  const res = await api('/api/config.php', 'POST', { bot_name: name });
  toast(res.message || (res.success ? 'Nome salvo!' : res.error), res.success ? 'success' : 'error');
}

async function saveKnowledge(type) {
  const data = { source_type: type };
  if (type === 'text') {
    const content = document.getElementById('kb-content').value.trim();
    if (!content) { toast('Escreva algum conteúdo', 'error'); return; }
    data.content = content;
  } else {
    const url = document.getElementById('kb-url').value.trim();
    if (!url) { toast('Informe uma URL', 'error'); return; }
    data.url = url;
    const btn = document.getElementById('btn-fetch');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Importando...'; }
  }
  const res = await api('/api/knowledge.php', 'POST', data);
  if (type === 'url') {
    const btn = document.getElementById('btn-fetch');
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-download"></i> Importar URL'; }
  }
  if (res.success) { toast(res.message || 'Salvo!', 'success'); if (res.preview) setTimeout(() => location.reload(), 1500); }
  else toast(res.error || 'Erro', 'error');
}
</script>

<?php include __DIR__ . '/../includes/layout-client-end.php'; ?>
