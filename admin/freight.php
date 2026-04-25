<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$activePage = 'freight';
$pageTitle  = 'Fretes por Bairro';
$freight    = dbFetchAll($db, 'SELECT * FROM freight ORDER BY neighborhood');

include __DIR__ . '/../includes/layout-admin.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">🚗 Fretes por Bairro</div>
    <div class="page-subtitle">Configure o valor do frete para cada região de entrega</div>
  </div>
  <button onclick="openFreightModal()" class="btn btn-primary">
    <i class="fa-solid fa-plus"></i> Novo Frete
  </button>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>Bairro</th><th>Cidade</th><th>Valor do Frete</th><th>Tempo de Entrega</th><th>Status</th><th>Ações</th></tr>
      </thead>
      <tbody>
      <?php if (empty($freight)): ?>
        <tr><td colspan="6">
          <div class="empty-state">
            <div class="empty-icon">🚗</div>
            <h3>Nenhum frete cadastrado</h3>
            <p>Adicione os bairros que você entrega e o valor do frete</p>
          </div>
        </td></tr>
      <?php else: foreach ($freight as $f): ?>
        <tr id="freight-row-<?= $f['id'] ?>">
          <td><strong><?= htmlspecialchars($f['neighborhood']) ?></strong></td>
          <td><?= htmlspecialchars($f['city'] ?: '—') ?></td>
          <td><?= $f['price'] == 0 ? '<span class="badge badge-success">Grátis</span>' : formatCurrency($f['price']) ?></td>
          <td><?= htmlspecialchars($f['delivery_time'] ?: '—') ?></td>
          <td><span class="badge <?= $f['is_active'] ? 'badge-success' : 'badge-secondary' ?>"><?= $f['is_active'] ? 'Ativo' : 'Inativo' ?></span></td>
          <td>
            <div class="flex gap-2">
              <button onclick="editFreight(<?= htmlspecialchars(json_encode($f)) ?>)" class="btn btn-outline btn-sm"><i class="fa-solid fa-pen"></i></button>
              <button onclick="deleteFreight(<?= $f['id'] ?>)" class="btn btn-outline btn-sm" style="color:var(--danger)"><i class="fa-solid fa-trash"></i></button>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL -->
<div id="freight-modal" class="modal-backdrop" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="freight-modal-title">Novo Frete</div>
      <button class="modal-close" onclick="closeModal('freight-modal')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="freight-id">
      <div class="form-group">
        <label class="form-label">Bairro *</label>
        <input type="text" id="freight-neighborhood" class="form-control" placeholder="Ex: Centro, Vila Nova...">
      </div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Cidade</label>
          <input type="text" id="freight-city" class="form-control" placeholder="Ex: São Paulo">
        </div>
        <div class="form-group">
          <label class="form-label">Valor do Frete (R$) <small>0 = grátis</small></label>
          <input type="number" id="freight-price" class="form-control" step="0.01" min="0" value="0">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Tempo estimado de entrega</label>
        <input type="text" id="freight-time" class="form-control" placeholder="Ex: 30-45 min, 1 hora...">
      </div>
      <div class="form-group">
        <label class="form-check">
          <input type="checkbox" id="freight-active" checked>
          <span>Bairro ativo para entrega</span>
        </label>
      </div>
    </div>
    <div class="modal-footer">
      <button onclick="closeModal('freight-modal')" class="btn btn-outline">Cancelar</button>
      <button onclick="saveFreight()" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
    </div>
  </div>
</div>

<script>
function openFreightModal() {
  document.getElementById('freight-modal-title').textContent = 'Novo Frete';
  document.getElementById('freight-id').value = '';
  document.getElementById('freight-neighborhood').value = '';
  document.getElementById('freight-city').value = '';
  document.getElementById('freight-price').value = '0';
  document.getElementById('freight-time').value = '';
  document.getElementById('freight-active').checked = true;
  openModal('freight-modal');
}

function editFreight(f) {
  document.getElementById('freight-modal-title').textContent = 'Editar Frete';
  document.getElementById('freight-id').value = f.id;
  document.getElementById('freight-neighborhood').value = f.neighborhood;
  document.getElementById('freight-city').value = f.city || '';
  document.getElementById('freight-price').value = f.price;
  document.getElementById('freight-time').value = f.delivery_time || '';
  document.getElementById('freight-active').checked = !!f.is_active;
  openModal('freight-modal');
}

async function saveFreight() {
  const id           = document.getElementById('freight-id').value;
  const neighborhood = document.getElementById('freight-neighborhood').value.trim();
  if (!neighborhood) { toast('Bairro é obrigatório', 'error'); return; }

  const data = {
    action: id ? 'update' : 'create',
    neighborhood,
    city:          document.getElementById('freight-city').value,
    price:         parseFloat(document.getElementById('freight-price').value) || 0,
    delivery_time: document.getElementById('freight-time').value,
    is_active:     document.getElementById('freight-active').checked ? 1 : 0
  };

  const url = id ? `/api/freight.php?id=${id}` : '/api/freight.php';
  const res = await api(url, 'POST', data);
  if (res.success) {
    toast(res.message, 'success');
    closeModal('freight-modal');
    setTimeout(() => location.reload(), 600);
  } else {
    toast(res.error || 'Erro ao salvar', 'error');
  }
}

async function deleteFreight(id) {
  if (!confirm('Remover este bairro?')) return;
  const res = await api(`/api/freight.php?id=${id}`, 'POST', { action: 'delete' });
  if (res.success) {
    toast('Removido', 'success');
    document.getElementById('freight-row-' + id)?.remove();
  }
}
</script>

<?php include __DIR__ . '/../includes/layout-admin-end.php'; ?>
