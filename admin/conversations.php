<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$activePage   = 'conversations';
$pageTitle    = 'Conversas';
$clientId     = (int)($_GET['client'] ?? 0);
$search       = trim($_GET['search'] ?? '');

// Lista de clientes com conversas
$where  = '1=1';
$params = [];
if ($search) { $where = '(name LIKE ? OR phone_number LIKE ?)'; $params = ["%$search%", "%$search%"]; }
$clients = dbFetchAll($db,
    "SELECT cl.*, MAX(c.created_at) AS last_msg, COUNT(c.id) AS msg_count
     FROM clients cl
     LEFT JOIN conversations c ON c.client_id = cl.id
     WHERE $where
     GROUP BY cl.id ORDER BY last_msg DESC LIMIT 50",
    $params
);

$selectedClient = null;
$messages       = [];
if ($clientId) {
    $selectedClient = dbFetchOne($db, 'SELECT * FROM clients WHERE id = ?', [$clientId]);
    $messages       = dbFetchAll($db,
        'SELECT * FROM conversations WHERE client_id = ? ORDER BY created_at ASC LIMIT 200',
        [$clientId]
    );
}

include __DIR__ . '/../includes/layout-admin.php';
?>
<style>
.conv-layout { display:grid; grid-template-columns:320px 1fr; gap:18px; height:calc(100vh - 160px); }
.conv-sidebar { overflow-y:auto; background:#fff; border-radius:14px; border:1px solid var(--border); }
.conv-client-item { padding:14px 16px; border-bottom:1px solid var(--border); cursor:pointer; transition:background .15s; }
.conv-client-item:hover, .conv-client-item.active { background:var(--accent-light); }
.conv-main { display:flex; flex-direction:column; background:#fff; border-radius:14px; border:1px solid var(--border); overflow:hidden; }
.conv-main-header { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.chat-area { flex:1; overflow-y:auto; background:#e5ddd5; padding:16px; }
@media(max-width:768px){ .conv-layout { grid-template-columns:1fr; height:auto; } .conv-main { min-height:400px; } }
</style>

<div class="page-header">
  <div>
    <div class="page-title">💬 Conversas</div>
    <div class="page-subtitle">Histórico de todas as conversas</div>
  </div>
</div>

<div class="conv-layout">
  <!-- Sidebar de clientes -->
  <div class="conv-sidebar">
    <div style="padding:12px">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" class="form-control" placeholder="Buscar cliente..." id="search-input"
               value="<?= htmlspecialchars($search) ?>"
               oninput="debounce(() => location.href='?search='+encodeURIComponent(this.value), 400)()">
      </div>
    </div>
    <?php if (empty($clients)): ?>
      <div class="empty-state" style="padding:40px 20px">
        <div class="empty-icon">💬</div>
        <h3>Sem conversas</h3>
      </div>
    <?php else: foreach ($clients as $c): ?>
      <div class="conv-client-item <?= $c['id'] == $clientId ? 'active' : '' ?>"
           onclick="location.href='?client=<?= $c['id'] ?><?= $search ? '&search='.urlencode($search) : '' ?>'">
        <div style="display:flex;align-items:center;justify-content:space-between">
          <div style="font-weight:600;font-size:14px"><?= htmlspecialchars($c['name'] ?: $c['phone_number']) ?></div>
          <div style="font-size:11px;color:var(--text-muted)"><?= timeAgo($c['last_msg']) ?></div>
        </div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:2px"><?= htmlspecialchars($c['phone_number']) ?></div>
        <div style="font-size:11px;color:var(--text-muted)"><?= $c['msg_count'] ?> mensagens</div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Área de conversa -->
  <div class="conv-main">
    <?php if ($selectedClient): ?>
      <div class="conv-main-header">
        <div>
          <div style="font-weight:700;font-size:15px"><?= htmlspecialchars($selectedClient['name'] ?: $selectedClient['phone_number']) ?></div>
          <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($selectedClient['phone_number']) ?> · <?= $selectedClient['total_messages'] ?> mensagens</div>
        </div>
        <div class="flex gap-2">
          <a href="/admin/clients.php?id=<?= $clientId ?>" class="btn btn-outline btn-sm">Ver Cliente</a>
          <button onclick="resetClientState(<?= $clientId ?>)" class="btn btn-outline btn-sm">Reset Estado</button>
          <button onclick="blockClient(<?= $clientId ?>, <?= $selectedClient['blocked'] ?>)" class="btn btn-<?= $selectedClient['blocked'] ? 'warning' : 'danger' ?> btn-sm">
            <?= $selectedClient['blocked'] ? 'Desbloquear' : 'Bloquear' ?>
          </button>
        </div>
      </div>
      <div class="chat-area" id="chat-area">
        <?php if (empty($messages)): ?>
          <p style="text-align:center;color:#888;font-size:13px;padding:30px">Sem mensagens</p>
        <?php else: foreach ($messages as $m): ?>
          <div style="margin-bottom:10px;display:flex;flex-direction:column;align-items:<?= $m['role'] === 'assistant' ? 'flex-end' : 'flex-start' ?>">
            <div class="chat-bubble <?= $m['role'] === 'assistant' ? 'assistant' : 'user' ?>">
              <?= nl2br(htmlspecialchars($m['message'])) ?>
            </div>
            <div class="chat-time">
              <?= $m['ai_used'] ? '[' . $m['ai_used'] . '] ' : '' ?>
              <?= date('d/m H:i', strtotime($m['created_at'])) ?>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    <?php else: ?>
      <div class="empty-state" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center">
        <div class="empty-icon">💬</div>
        <h3>Selecione uma conversa</h3>
        <p>Clique em um cliente para ver o histórico</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const chat = document.getElementById('chat-area');
  if (chat) chat.scrollTop = chat.scrollHeight;
});

async function blockClient(id, isBlocked) {
  const action = isBlocked ? 'unblock' : 'block';
  const res = await api(`/api/clients.php?id=${id}`, 'POST', { action });
  if (res.success) { toast(res.message, 'success'); setTimeout(() => location.reload(), 600); }
}

async function resetClientState(id) {
  const res = await api(`/api/clients.php?id=${id}`, 'POST', { action: 'reset_state' });
  if (res.success) toast('Estado resetado', 'success');
}
</script>

<?php include __DIR__ . '/../includes/layout-admin-end.php'; ?>
