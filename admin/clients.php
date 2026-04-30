<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$activePage = 'clients';
$pageTitle  = 'Clientes';
$search     = trim($_GET['search'] ?? '');
$viewId     = (int)($_GET['id'] ?? 0);

$where  = '1=1';
$params = [];
if ($search) { $where = '(name LIKE ? OR phone_number LIKE ? OR tags LIKE ?)'; $params = ["%$search%","%$search%","%$search%"]; }
$clients = dbFetchAll($db, "SELECT * FROM clients WHERE $where ORDER BY last_interaction DESC LIMIT 100", $params);

$viewClient = null;
if ($viewId) {
    $viewClient = dbFetchOne($db, 'SELECT * FROM clients WHERE id = ?', [$viewId]);
}

include __DIR__ . '/../includes/layout-admin.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">👥 Clientes</div>
    <div class="page-subtitle">Gerenciar todos os clientes que interagiram com o bot</div>
  </div>
  <div class="search-box">
    <span class="search-icon">🔍</span>
    <form method="GET">
      <input type="text" name="search" class="form-control" placeholder="Buscar por nome ou telefone..."
             value="<?= htmlspecialchars($search) ?>" style="width:260px">
    </form>
  </div>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>Nome</th><th>Telefone</th><th>Mensagens</th><th>Última Interação</th><th>Status</th><th>Ações</th></tr>
      </thead>
      <tbody>
      <?php if (empty($clients)): ?>
        <tr><td colspan="6">
          <div class="empty-state">
            <div class="empty-icon">👥</div>
            <h3>Nenhum cliente encontrado</h3>
          </div>
        </td></tr>
      <?php else: foreach ($clients as $c): ?>
        <tr>
          <td>
            <div style="font-weight:600"><?= htmlspecialchars($c['name'] ?: '—') ?></div>
            <?php if ($c['nickname'] && $c['nickname'] !== $c['name']): ?>
              <div style="font-size:12px;color:var(--text-muted)">Apelido: <?= htmlspecialchars($c['nickname']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($c['phone_number']) ?></td>
          <td><?= number_format($c['total_messages']) ?></td>
          <td><?= timeAgo($c['last_interaction']) ?></td>
          <td>
            <?php if ($c['blocked']): ?>
              <span class="badge badge-danger">Bloqueado</span>
            <?php else: ?>
              <span class="badge badge-success">Ativo</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="flex gap-2">
              <a href="/admin/conversations.php?client=<?= $c['id'] ?>" class="btn btn-outline btn-sm" title="Conversas">
                <i class="fa-solid fa-comments"></i>
              </a>
              <button onclick="editClientName(<?= $c['id'] ?>, '<?= addslashes($c['name'] ?? '') ?>')" class="btn btn-outline btn-sm" title="Editar nome">
                <i class="fa-solid fa-pen"></i>
              </button>
              <button onclick="toggleBlock(<?= $c['id'] ?>, <?= (int)$c['blocked'] ?>)" class="btn btn-outline btn-sm"
                      title="<?= $c['blocked'] ? 'Desbloquear' : 'Bloquear' ?>">
                <i class="fa-solid fa-<?= $c['blocked'] ? 'lock-open' : 'ban' ?>"></i>
              </button>
              <button onclick="deleteClient(<?= $c['id'] ?>)" class="btn btn-outline btn-sm" style="color:var(--danger)" title="Remover">
                <i class="fa-solid fa-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal editar nome -->
<div id="edit-name-modal" class="modal-backdrop" style="display:none">
  <div class="modal" style="max-width:400px">
    <div class="modal-header">
      <div class="modal-title">Editar Nome do Cliente</div>
      <button class="modal-close" onclick="closeModal('edit-name-modal')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="edit-client-id">
      <div class="form-group">
        <label class="form-label">Nome</label>
        <input type="text" id="edit-client-name" class="form-control">
      </div>
    </div>
    <div class="modal-footer">
      <button onclick="closeModal('edit-name-modal')" class="btn btn-outline">Cancelar</button>
      <button onclick="saveClientName()" class="btn btn-primary">Salvar</button>
    </div>
  </div>
</div>

<script>
function editClientName(id, name) {
  document.getElementById('edit-client-id').value = id;
  document.getElementById('edit-client-name').value = name;
  openModal('edit-name-modal');
}

async function saveClientName() {
  const id   = document.getElementById('edit-client-id').value;
  const name = document.getElementById('edit-client-name').value.trim();
  const res  = await api(`/api/clients.php?id=${id}`, 'POST', { action: 'update_name', name });
  if (res.success) { toast('Nome atualizado', 'success'); closeModal('edit-name-modal'); setTimeout(() => location.reload(), 500); }
  else toast(res.error, 'error');
}

async function toggleBlock(id, blocked) {
  const res = await api(`/api/clients.php?id=${id}`, 'POST', { action: blocked ? 'unblock' : 'block' });
  if (res.success) { toast(res.message, 'success'); setTimeout(() => location.reload(), 500); }
}

async function deleteClient(id) {
  if (!confirm('Remover este cliente e todo seu histórico?')) return;
  const res = await api(`/api/clients.php?id=${id}`, 'POST', { action: 'delete' });
  if (res.success) { toast('Cliente removido', 'success'); setTimeout(() => location.reload(), 500); }
}
</script>

<?php include __DIR__ . '/../includes/layout-admin-end.php'; ?>
