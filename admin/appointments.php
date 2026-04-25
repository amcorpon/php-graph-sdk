<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$activePage = 'appointments';
$pageTitle  = 'Agendamentos';
$tab        = $_GET['tab'] ?? 'appointments';
$month      = $_GET['month'] ?? date('Y-m');
$prevMonth  = date('Y-m', strtotime($month . '-01 -1 month'));
$nextMonth  = date('Y-m', strtotime($month . '-01 +1 month'));

$appointments = dbFetchAll($db,
    "SELECT a.*, c.name AS client_name, c.phone_number
     FROM appointments a JOIN clients c ON c.id = a.client_id
     WHERE DATE_FORMAT(a.appointment_date, '%Y-%m') = ?
     ORDER BY a.appointment_date, a.appointment_time",
    [$month]
);
$services = dbFetchAll($db, 'SELECT * FROM services ORDER BY sort_order, name');

include __DIR__ . '/../includes/layout-admin.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">📅 Agendamentos</div>
    <div class="page-subtitle">Visualize e gerencie a agenda</div>
  </div>
</div>

<div class="tabs">
  <button class="tab-btn <?= $tab === 'appointments' ? 'active' : '' ?>" onclick="switchTab('appointments')">📅 Agenda</button>
  <button class="tab-btn <?= $tab === 'services' ? 'active' : '' ?>" onclick="switchTab('services')">✂️ Serviços</button>
</div>

<!-- AGENDA -->
<div id="tab-appointments" style="display:<?= $tab === 'appointments' ? 'block' : 'none' ?>">

  <!-- Navegação por mês -->
  <div class="flex-between mb-3">
    <a href="?tab=appointments&month=<?= $prevMonth ?>" class="btn btn-outline btn-sm">← Anterior</a>
    <h3 style="font-size:16px;font-weight:700"><?= date('F Y', strtotime($month . '-01')) ?></h3>
    <a href="?tab=appointments&month=<?= $nextMonth ?>" class="btn btn-outline btn-sm">Próximo →</a>
  </div>

  <?php if (empty($appointments)): ?>
    <div class="empty-state">
      <div class="empty-icon">📅</div>
      <h3>Nenhum agendamento em <?= date('F Y', strtotime($month . '-01')) ?></h3>
    </div>
  <?php else: ?>
  <div class="card">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>#</th><th>Cliente</th><th>Serviço</th><th>Data</th><th>Horário</th><th>Duração</th><th>Status</th><th>Ações</th></tr>
        </thead>
        <tbody>
        <?php foreach ($appointments as $a): ?>
          <tr>
            <td>#<?= $a['id'] ?></td>
            <td>
              <div><?= htmlspecialchars($a['client_name'] ?: '—') ?></div>
              <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($a['phone_number']) ?></div>
            </td>
            <td><?= htmlspecialchars($a['service_name'] ?: '—') ?></td>
            <td><?= date('d/m/Y', strtotime($a['appointment_date'])) ?></td>
            <td><?= substr($a['appointment_time'], 0, 5) ?></td>
            <td><?= $a['duration_minutes'] ?>min</td>
            <td><?= getStatusBadge($a['status']) ?></td>
            <td>
              <select onchange="updateApptStatus(<?= $a['id'] ?>, this.value)" class="form-select"
                      style="width:auto;padding:4px 8px;font-size:12px">
                <option value="confirmed" <?= $a['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmado</option>
                <option value="completed" <?= $a['status'] === 'completed' ? 'selected' : '' ?>>Concluído</option>
                <option value="cancelled" <?= $a['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                <option value="no_show"   <?= $a['status'] === 'no_show'   ? 'selected' : '' ?>>Não compareceu</option>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- SERVIÇOS -->
<div id="tab-services" style="display:<?= $tab === 'services' ? 'block' : 'none' ?>">
  <div class="flex-between mb-3">
    <div style="font-size:14px;color:var(--text-muted)">Serviços que o bot oferece para agendamento</div>
    <button onclick="openServiceModal()" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-plus"></i> Novo Serviço
    </button>
  </div>

  <div class="card">
    <div class="table-wrapper">
      <table>
        <thead><tr><th>#</th><th>Serviço</th><th>Duração</th><th>Preço</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody id="services-tbody">
        <?php if (empty($services)): ?>
          <tr><td colspan="6">
            <div class="empty-state">
              <div class="empty-icon">✂️</div>
              <h3>Nenhum serviço cadastrado</h3>
              <p>Adicione os serviços que você oferece</p>
            </div>
          </td></tr>
        <?php else: foreach ($services as $s): ?>
          <tr id="service-row-<?= $s['id'] ?>">
            <td>#<?= $s['id'] ?></td>
            <td>
              <div style="font-weight:600"><?= htmlspecialchars($s['name']) ?></div>
              <?php if ($s['description']): ?><div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($s['description']) ?></div><?php endif; ?>
            </td>
            <td><?= $s['duration_minutes'] ?> min</td>
            <td><?= $s['price'] ? formatCurrency($s['price']) : '—' ?></td>
            <td><span class="badge <?= $s['is_active'] ? 'badge-success' : 'badge-secondary' ?>"><?= $s['is_active'] ? 'Ativo' : 'Inativo' ?></span></td>
            <td>
              <div class="flex gap-2">
                <button onclick="editService(<?= htmlspecialchars(json_encode($s)) ?>)" class="btn btn-outline btn-sm"><i class="fa-solid fa-pen"></i></button>
                <button onclick="deleteService(<?= $s['id'] ?>)" class="btn btn-outline btn-sm" style="color:var(--danger)"><i class="fa-solid fa-trash"></i></button>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- MODAL SERVIÇO -->
<div id="service-modal" class="modal-backdrop" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="service-modal-title">Novo Serviço</div>
      <button class="modal-close" onclick="closeModal('service-modal')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="service-id">
      <div class="form-group">
        <label class="form-label">Nome do Serviço *</label>
        <input type="text" id="service-name" class="form-control" placeholder="Ex: Corte de Cabelo, Consulta...">
      </div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Duração (minutos)</label>
          <input type="number" id="service-duration" class="form-control" value="60" min="10">
        </div>
        <div class="form-group">
          <label class="form-label">Preço <small>(opcional)</small></label>
          <input type="number" id="service-price" class="form-control" step="0.01" min="0" placeholder="0.00">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Descrição</label>
        <textarea id="service-description" class="form-control" rows="3"></textarea>
      </div>
      <div class="form-group">
        <label class="form-check">
          <input type="checkbox" id="service-active" checked>
          <span>Serviço ativo</span>
        </label>
      </div>
    </div>
    <div class="modal-footer">
      <button onclick="closeModal('service-modal')" class="btn btn-outline">Cancelar</button>
      <button onclick="saveService()" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
    </div>
  </div>
</div>

<script>
function switchTab(tab) {
  document.getElementById('tab-appointments').style.display = tab === 'appointments' ? 'block' : 'none';
  document.getElementById('tab-services').style.display     = tab === 'services'     ? 'block' : 'none';
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  event.target.classList.add('active');
}

async function updateApptStatus(id, status) {
  const res = await api(`/api/appointments.php?id=${id}`, 'POST', { action: 'update_status', status });
  if (res.success) toast('Status atualizado!', 'success');
  else toast(res.error || 'Erro', 'error');
}

function openServiceModal() {
  document.getElementById('service-modal-title').textContent = 'Novo Serviço';
  document.getElementById('service-id').value = '';
  document.getElementById('service-name').value = '';
  document.getElementById('service-duration').value = '60';
  document.getElementById('service-price').value = '';
  document.getElementById('service-description').value = '';
  document.getElementById('service-active').checked = true;
  openModal('service-modal');
}

function editService(s) {
  document.getElementById('service-modal-title').textContent = 'Editar Serviço';
  document.getElementById('service-id').value = s.id;
  document.getElementById('service-name').value = s.name;
  document.getElementById('service-duration').value = s.duration_minutes;
  document.getElementById('service-price').value = s.price || '';
  document.getElementById('service-description').value = s.description || '';
  document.getElementById('service-active').checked = !!s.is_active;
  openModal('service-modal');
}

async function saveService() {
  const name = document.getElementById('service-name').value.trim();
  if (!name) { toast('Nome é obrigatório', 'error'); return; }
  const data = {
    action: 'save_service',
    id: document.getElementById('service-id').value || null,
    name,
    duration_minutes: parseInt(document.getElementById('service-duration').value) || 60,
    price: parseFloat(document.getElementById('service-price').value) || null,
    description: document.getElementById('service-description').value,
    is_active: document.getElementById('service-active').checked ? 1 : 0
  };
  const res = await api('/api/appointments.php', 'POST', data);
  if (res.success) {
    toast(res.message, 'success');
    closeModal('service-modal');
    setTimeout(() => location.reload(), 600);
  } else {
    toast(res.error || 'Erro', 'error');
  }
}

async function deleteService(id) {
  if (!confirm('Remover este serviço?')) return;
  const res = await api('/api/appointments.php', 'POST', { action: 'delete_service', id });
  if (res.success) {
    toast('Removido', 'success');
    document.getElementById('service-row-' + id)?.remove();
  }
}
</script>

<?php include __DIR__ . '/../includes/layout-admin-end.php'; ?>
