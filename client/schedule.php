<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireClient();

$activePage  = 'schedule';
$pageTitle   = 'Agenda e Serviços';
$tab         = $_GET['tab'] ?? 'agenda';
$month       = $_GET['month'] ?? date('Y-m');
$prevMonth   = date('Y-m', strtotime($month . '-01 -1 month'));
$nextMonth   = date('Y-m', strtotime($month . '-01 +1 month'));

$appointments = dbFetchAll($db,
    "SELECT a.*, c.name AS client_name, c.phone_number
     FROM appointments a JOIN clients c ON c.id = a.client_id
     WHERE DATE_FORMAT(a.appointment_date, '%Y-%m') = ?
     ORDER BY a.appointment_date, a.appointment_time",
    [$month]
);
$services = dbFetchAll($db, 'SELECT * FROM services ORDER BY sort_order, name');
$hours    = dbFetchAll($db, 'SELECT * FROM business_hours ORDER BY day_of_week');
$dayNames = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

include __DIR__ . '/../includes/layout-client.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">📅 Agenda e Serviços</div>
    <div class="page-subtitle">Gerenciar agendamentos e horários disponíveis</div>
  </div>
</div>

<div class="tabs">
  <button class="tab-btn <?= $tab === 'agenda' ? 'active' : '' ?>"   onclick="switchTab('agenda', event)">📅 Agenda</button>
  <button class="tab-btn <?= $tab === 'services' ? 'active' : '' ?>" onclick="switchTab('services', event)">✂️ Serviços</button>
  <button class="tab-btn <?= $tab === 'hours' ? 'active' : '' ?>"    onclick="switchTab('hours', event)">🕐 Horários</button>
</div>

<!-- AGENDA -->
<div id="tab-agenda" style="display:<?= $tab === 'agenda' ? 'block' : 'none' ?>">
  <div class="flex-between mb-3">
    <a href="?tab=agenda&month=<?= $prevMonth ?>" class="btn btn-outline btn-sm">← Anterior</a>
    <h3 style="font-size:16px;font-weight:700"><?= date('F Y', strtotime($month . '-01')) ?></h3>
    <a href="?tab=agenda&month=<?= $nextMonth ?>" class="btn btn-outline btn-sm">Próximo →</a>
  </div>

  <?php if (empty($appointments)): ?>
    <div class="empty-state">
      <div class="empty-icon">📅</div>
      <h3>Sem agendamentos neste mês</h3>
    </div>
  <?php else: ?>
  <div class="card">
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Cliente</th><th>Serviço</th><th>Data</th><th>Horário</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($appointments as $a): ?>
          <tr>
            <td>
              <div style="font-weight:600"><?= htmlspecialchars($a['client_name'] ?: '—') ?></div>
              <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($a['phone_number']) ?></div>
            </td>
            <td><?= htmlspecialchars($a['service_name'] ?: '—') ?></td>
            <td><?= date('d/m/Y', strtotime($a['appointment_date'])) ?></td>
            <td><?= substr($a['appointment_time'],0,5) ?></td>
            <td><?= getStatusBadge($a['status']) ?></td>
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
    <p style="font-size:13px;color:var(--text-muted)"><?= count($services) ?> serviços cadastrados</p>
    <button onclick="openServiceModal()" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Novo Serviço</button>
  </div>
  <div class="card">
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Serviço</th><th>Duração</th><th>Preço</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
        <?php if (empty($services)): ?>
          <tr><td colspan="5">
            <div class="empty-state">
              <div class="empty-icon">✂️</div>
              <h3>Nenhum serviço cadastrado</h3>
              <p>Adicione os serviços que você oferece para que os clientes possam agendar</p>
            </div>
          </td></tr>
        <?php else: foreach ($services as $s): ?>
          <tr id="svc-<?= $s['id'] ?>">
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

<!-- HORÁRIOS -->
<div id="tab-hours" style="display:<?= $tab === 'hours' ? 'block' : 'none' ?>">
  <div class="card">
    <div class="card-header">
      <div class="card-title">🕐 Horários de Atendimento</div>
    </div>
    <div class="card-body">
      <div class="alert alert-info">Configure os dias e horários em que você atende para o bot verificar disponibilidade automaticamente.</div>
      <form id="hours-form">
        <div style="overflow-x:auto">
          <table>
            <thead><tr><th>Dia</th><th>Aberto</th><th>Início</th><th>Fim</th></tr></thead>
            <tbody>
            <?php foreach ($hours as $h): ?>
            <tr>
              <td><strong><?= $dayNames[$h['day_of_week']] ?></strong></td>
              <td>
                <label class="toggle-switch" style="transform:scale(.85)">
                  <input type="checkbox" name="hours[<?= $h['day_of_week'] ?>][is_open]" value="1"
                         class="hour-toggle" data-day="<?= $h['day_of_week'] ?>" <?= $h['is_open'] ? 'checked' : '' ?>>
                  <span class="toggle-slider"></span>
                </label>
              </td>
              <td><input type="time" name="hours[<?= $h['day_of_week'] ?>][open_time]" class="form-control" style="width:110px"
                         value="<?= substr($h['open_time'],0,5) ?>" <?= !$h['is_open'] ? 'disabled' : '' ?>></td>
              <td><input type="time" name="hours[<?= $h['day_of_week'] ?>][close_time]" class="form-control" style="width:110px"
                         value="<?= substr($h['close_time'],0,5) ?>" <?= !$h['is_open'] ? 'disabled' : '' ?>></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button type="submit" class="btn btn-primary mt-2"><i class="fa-solid fa-floppy-disk"></i> Salvar Horários</button>
      </form>
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
      <div class="form-group"><label class="form-label">Nome *</label><input type="text" id="service-name" class="form-control"></div>
      <div class="grid-2">
        <div class="form-group"><label class="form-label">Duração (min)</label><input type="number" id="service-duration" class="form-control" value="60" min="10"></div>
        <div class="form-group"><label class="form-label">Preço (opcional)</label><input type="number" id="service-price" class="form-control" step="0.01" min="0"></div>
      </div>
      <div class="form-group"><label class="form-label">Descrição</label><textarea id="service-description" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer">
      <button onclick="closeModal('service-modal')" class="btn btn-outline">Cancelar</button>
      <button onclick="saveService()" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
    </div>
  </div>
</div>

<script>
function switchTab(tab, e) {
  ['agenda','services','hours'].forEach(t => {
    document.getElementById('tab-'+t).style.display = t === tab ? 'block' : 'none';
  });
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  if (e) e.target.classList.add('active');
}

document.querySelectorAll('.hour-toggle').forEach(toggle => {
  toggle.addEventListener('change', function() {
    const row = this.closest('tr');
    row.querySelectorAll('input[type=time]').forEach(i => i.disabled = !this.checked);
  });
});

document.getElementById('hours-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const hours = [];
  for (let d = 0; d <= 6; d++) {
    hours.push({
      day_of_week: d,
      is_open:     form.querySelector(`[name="hours[${d}][is_open]"]`)?.checked ? 1 : 0,
      open_time:   (form.querySelector(`[name="hours[${d}][open_time]"]`)?.value || '08:00') + ':00',
      close_time:  (form.querySelector(`[name="hours[${d}][close_time]"]`)?.value || '18:00') + ':00'
    });
  }
  const res = await api('/api/config.php', 'POST', { business_hours: hours });
  toast(res.message || (res.success ? 'Horários salvos!' : res.error), res.success ? 'success' : 'error');
});

function openServiceModal() {
  document.getElementById('service-modal-title').textContent = 'Novo Serviço';
  document.getElementById('service-id').value = '';
  document.getElementById('service-name').value = '';
  document.getElementById('service-duration').value = '60';
  document.getElementById('service-price').value = '';
  document.getElementById('service-description').value = '';
  openModal('service-modal');
}

function editService(s) {
  document.getElementById('service-modal-title').textContent = 'Editar Serviço';
  document.getElementById('service-id').value = s.id;
  document.getElementById('service-name').value = s.name;
  document.getElementById('service-duration').value = s.duration_minutes;
  document.getElementById('service-price').value = s.price || '';
  document.getElementById('service-description').value = s.description || '';
  openModal('service-modal');
}

async function saveService() {
  const name = document.getElementById('service-name').value.trim();
  if (!name) { toast('Nome é obrigatório', 'error'); return; }
  const id = document.getElementById('service-id').value;
  const res = await api('/api/appointments.php', 'POST', {
    action: 'save_service',
    id: id || null, name,
    duration_minutes: parseInt(document.getElementById('service-duration').value) || 60,
    price: parseFloat(document.getElementById('service-price').value) || null,
    description: document.getElementById('service-description').value,
    is_active: 1
  });
  if (res.success) { toast(res.message, 'success'); closeModal('service-modal'); setTimeout(() => location.reload(), 600); }
  else toast(res.error || 'Erro', 'error');
}

async function deleteService(id) {
  if (!confirm('Remover serviço?')) return;
  const res = await api('/api/appointments.php', 'POST', { action: 'delete_service', id });
  if (res.success) { toast('Removido', 'success'); document.getElementById('svc-'+id)?.remove(); }
}
</script>

<?php include __DIR__ . '/../includes/layout-client-end.php'; ?>
