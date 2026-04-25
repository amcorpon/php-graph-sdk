<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$activePage = 'dashboard';
$pageTitle  = 'Dashboard';

// Estatísticas
$totalClients  = dbFetchOne($db, 'SELECT COUNT(*) as c FROM clients')['c'] ?? 0;
$totalMessages = dbFetchOne($db, 'SELECT COUNT(*) as c FROM conversations')['c'] ?? 0;
$todayMessages = dbFetchOne($db, "SELECT COUNT(*) as c FROM conversations WHERE DATE(created_at) = CURDATE()")['c'] ?? 0;
$totalOrders   = dbFetchOne($db, 'SELECT COUNT(*) as c FROM orders')['c'] ?? 0;
$pendingOrders = dbFetchOne($db, "SELECT COUNT(*) as c FROM orders WHERE status NOT IN ('delivered','cancelled')")['c'] ?? 0;
$todayRevenue  = dbFetchOne($db, "SELECT COALESCE(SUM(total),0) as r FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'")['r'] ?? 0;
$totalAppts    = dbFetchOne($db, 'SELECT COUNT(*) as c FROM appointments')['c'] ?? 0;
$todayAppts    = dbFetchOne($db, "SELECT COUNT(*) as c FROM appointments WHERE appointment_date = CURDATE() AND status = 'confirmed'")['c'] ?? 0;

$recentOrders = dbFetchAll($db,
    "SELECT o.*, c.name AS client_name, c.phone_number FROM orders o
     JOIN clients c ON c.id = o.client_id ORDER BY o.created_at DESC LIMIT 5"
);
$recentAppts = dbFetchAll($db,
    "SELECT a.*, c.name AS client_name FROM appointments a
     JOIN clients c ON c.id = a.client_id
     WHERE a.appointment_date >= CURDATE() AND a.status = 'confirmed'
     ORDER BY a.appointment_date, a.appointment_time LIMIT 5"
);
$recentConvs = dbFetchAll($db,
    "SELECT c2.*, cl.name AS client_name, cl.phone_number
     FROM (SELECT client_id, MAX(created_at) AS last_msg FROM conversations GROUP BY client_id ORDER BY last_msg DESC LIMIT 5) c2
     JOIN clients cl ON cl.id = c2.client_id"
);
$botStatus = dbFetchOne($db, 'SELECT * FROM bot_status LIMIT 1');

include __DIR__ . '/../includes/layout-admin.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Dashboard</div>
    <div class="page-subtitle">Visão geral do seu bot</div>
  </div>
  <div class="flex gap-2">
    <?php if ($botStatus): ?>
      <?php $connected = $botStatus['is_connected'] && (time() - strtotime($botStatus['last_ping'])) < 60; ?>
      <span class="bot-status-pill <?= $connected ? 'connected' : 'disconnected' ?>">
        <span class="status-dot <?= $connected ? 'green' : 'red' ?>"></span>
        <?= $connected ? 'Bot Online' : 'Bot Offline' ?>
      </span>
    <?php endif; ?>
    <a href="/admin/bot-config.php" class="btn btn-primary">
      <i class="fa-solid fa-gear"></i> Configurar Bot
    </a>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon green">👥</div>
    <div>
      <div class="stat-label">Clientes</div>
      <div class="stat-value"><?= number_format($totalClients) ?></div>
      <div class="stat-sub">Total cadastrados</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue">💬</div>
    <div>
      <div class="stat-label">Mensagens Hoje</div>
      <div class="stat-value"><?= number_format($todayMessages) ?></div>
      <div class="stat-sub"><?= number_format($totalMessages) ?> no total</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow">🛍️</div>
    <div>
      <div class="stat-label">Pedidos</div>
      <div class="stat-value"><?= number_format($pendingOrders) ?></div>
      <div class="stat-sub"><?= number_format($totalOrders) ?> no total</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">💰</div>
    <div>
      <div class="stat-label">Receita Hoje</div>
      <div class="stat-value" style="font-size:18px"><?= formatCurrency($todayRevenue) ?></div>
      <div class="stat-sub">Pedidos confirmados</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">📅</div>
    <div>
      <div class="stat-label">Agendamentos Hoje</div>
      <div class="stat-value"><?= number_format($todayAppts) ?></div>
      <div class="stat-sub"><?= number_format($totalAppts) ?> no total</div>
    </div>
  </div>
</div>

<?php if ($botStatus && $botStatus['pairing_code'] && !$botStatus['is_connected']): ?>
<div class="alert alert-warning" style="margin-bottom:24px;">
  <div>
    <strong>🔗 Código de Emparelhamento Disponível</strong>
    <p style="margin-top:4px;">Abra o WhatsApp → <strong>Dispositivos conectados → Conectar dispositivo → Conectar com número de telefone</strong></p>
    <div class="pairing-code-display" style="font-size:28px;padding:12px 20px;margin-top:10px;">
      <?= htmlspecialchars($botStatus['pairing_code']) ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="grid-2" style="gap:22px">

  <!-- Pedidos Recentes -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">🛍️ Pedidos Recentes</div>
      <a href="/admin/orders.php" class="btn btn-outline btn-sm">Ver todos</a>
    </div>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>#</th><th>Cliente</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (empty($recentOrders)): ?>
          <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:30px">Nenhum pedido ainda</td></tr>
        <?php else: foreach ($recentOrders as $o): ?>
          <tr>
            <td>#<?= $o['id'] ?></td>
            <td><?= htmlspecialchars($o['client_name'] ?: $o['phone_number']) ?></td>
            <td><?= formatCurrency($o['total']) ?></td>
            <td><?= getStatusBadge($o['status']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Próximos Agendamentos -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">📅 Próximos Agendamentos</div>
      <a href="/admin/appointments.php" class="btn btn-outline btn-sm">Ver todos</a>
    </div>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Cliente</th><th>Serviço</th><th>Data/Hora</th></tr></thead>
        <tbody>
        <?php if (empty($recentAppts)): ?>
          <tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:30px">Nenhum agendamento próximo</td></tr>
        <?php else: foreach ($recentAppts as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['client_name'] ?: '—') ?></td>
            <td><?= htmlspecialchars($a['service_name'] ?: '—') ?></td>
            <td><?= formatDateBR($a['appointment_date']) ?> <?= formatTime($a['appointment_time']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Conversas Recentes -->
<div class="card mt-3">
  <div class="card-header">
    <div class="card-title">💬 Clientes Ativos Recentemente</div>
    <a href="/admin/conversations.php" class="btn btn-outline btn-sm">Ver todas</a>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Cliente</th><th>Telefone</th><th>Última Mensagem</th><th>Ação</th></tr></thead>
      <tbody>
      <?php if (empty($recentConvs)): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:30px">Nenhuma conversa ainda</td></tr>
      <?php else: foreach ($recentConvs as $c): ?>
        <tr>
          <td><?= htmlspecialchars($c['client_name'] ?: '—') ?></td>
          <td><?= htmlspecialchars($c['phone_number']) ?></td>
          <td><?= timeAgo($c['last_msg']) ?></td>
          <td><a href="/admin/conversations.php?client=<?= $c['client_id'] ?>" class="btn btn-outline btn-sm">Ver</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
function formatDateBR($d) { return $d ? date('d/m/Y', strtotime($d)) : ''; }
include __DIR__ . '/../includes/layout-admin-end.php';
?>
