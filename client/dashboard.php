<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireClient();

$activePage = 'dashboard';
$pageTitle  = 'Visão Geral';
$config     = getConfig($db);
$botStatus  = getBotStatus($db);
$kb         = dbFetchOne($db, 'SELECT * FROM knowledge_base LIMIT 1');

$totalClients  = dbFetchOne($db, 'SELECT COUNT(*) as c FROM clients')['c'] ?? 0;
$todayMessages = dbFetchOne($db, "SELECT COUNT(*) as c FROM conversations WHERE DATE(created_at) = CURDATE()")['c'] ?? 0;
$pendingOrders = dbFetchOne($db, "SELECT COUNT(*) as c FROM orders WHERE status NOT IN ('delivered','cancelled')")['c'] ?? 0;
$todayAppts    = dbFetchOne($db, "SELECT COUNT(*) as c FROM appointments WHERE appointment_date = CURDATE() AND status = 'confirmed'")['c'] ?? 0;

include __DIR__ . '/../includes/layout-client.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Olá, <?= htmlspecialchars($_SESSION['client_business'] ?? 'Cliente') ?>! 👋</div>
    <div class="page-subtitle">Aqui está um resumo do seu bot hoje</div>
  </div>
</div>

<!-- Status do bot -->
<div class="card mb-3">
  <div class="card-body">
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
      <div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:6px">Nome do Bot:</p>
        <p style="font-size:20px;font-weight:800">🤖 <?= htmlspecialchars($config['bot_name'] ?? '—') ?></p>
      </div>
      <div style="margin-left:auto">
        <?php $connected = $botStatus && $botStatus['is_connected'] && (time() - strtotime($botStatus['last_ping'])) < 60; ?>
        <span class="bot-status-pill <?= $connected ? 'connected' : 'disconnected' ?>">
          <span class="status-dot <?= $connected ? 'green' : 'red' ?>"></span>
          <?= $connected ? 'Bot Online' : 'Bot Offline' ?>
        </span>
        <?php if ($connected && $botStatus['phone_number']): ?>
          <p style="font-size:12px;color:var(--text-muted);margin-top:4px">📱 <?= htmlspecialchars($botStatus['phone_number']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <?php
    $modes = explode(',', $config['bot_mode'] ?? 'qa');
    $modeLabels = ['qa' => '❓ Dúvidas', 'orders' => '🛍️ Pedidos', 'appointments' => '📅 Agendamentos'];
    ?>
    <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
      <?php foreach ($modes as $m): ?>
        <span class="badge badge-info"><?= $modeLabels[$m] ?? $m ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon green">👥</div>
    <div>
      <div class="stat-label">Clientes</div>
      <div class="stat-value"><?= number_format($totalClients) ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue">💬</div>
    <div>
      <div class="stat-label">Msgs Hoje</div>
      <div class="stat-value"><?= number_format($todayMessages) ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow">🛍️</div>
    <div>
      <div class="stat-label">Pedidos Abertos</div>
      <div class="stat-value"><?= number_format($pendingOrders) ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">📅</div>
    <div>
      <div class="stat-label">Agend. Hoje</div>
      <div class="stat-value"><?= number_format($todayAppts) ?></div>
    </div>
  </div>
</div>

<!-- Atalhos rápidos -->
<div class="grid-3" style="gap:16px">
  <a href="/client/knowledge.php" class="card" style="text-decoration:none;color:inherit">
    <div class="card-body" style="text-align:center;padding:24px">
      <div style="font-size:32px;margin-bottom:8px">📚</div>
      <div style="font-weight:700">Base de Conhecimento</div>
      <div style="font-size:12px;color:var(--text-muted);margin-top:4px">
        <?php if ($kb): ?>
          Tipo: <?= $kb['source_type'] === 'url' ? '🌐 URL' : '📝 Texto' ?>
          <?php if ($kb['source_type'] === 'url' && $kb['last_url_fetch']): ?>
            · Atualizado <?= timeAgo($kb['last_url_fetch']) ?>
          <?php endif; ?>
        <?php else: ?>
          Não configurada
        <?php endif; ?>
      </div>
    </div>
  </a>
  <a href="/client/products.php" class="card" style="text-decoration:none;color:inherit">
    <div class="card-body" style="text-align:center;padding:24px">
      <div style="font-size:32px;margin-bottom:8px">📦</div>
      <div style="font-weight:700">Produtos e Fretes</div>
      <?php $prodCount = dbFetchOne($db, 'SELECT COUNT(*) as c FROM products')['c'] ?? 0; ?>
      <div style="font-size:12px;color:var(--text-muted);margin-top:4px"><?= $prodCount ?> produtos cadastrados</div>
    </div>
  </a>
  <a href="/client/schedule.php" class="card" style="text-decoration:none;color:inherit">
    <div class="card-body" style="text-align:center;padding:24px">
      <div style="font-size:32px;margin-bottom:8px">📅</div>
      <div style="font-weight:700">Agenda e Serviços</div>
      <?php $svcCount = dbFetchOne($db, 'SELECT COUNT(*) as c FROM services')['c'] ?? 0; ?>
      <div style="font-size:12px;color:var(--text-muted);margin-top:4px"><?= $svcCount ?> serviços cadastrados</div>
    </div>
  </a>
</div>

<?php include __DIR__ . '/../includes/layout-client-end.php'; ?>
