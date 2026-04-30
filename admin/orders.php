<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$activePage = 'orders';
$pageTitle  = 'Pedidos';
$statusFilter = $_GET['status'] ?? '';

$where  = ['1=1'];
$params = [];
if ($statusFilter) { $where[] = 'o.status = ?'; $params[] = $statusFilter; }
$whereStr = implode(' AND ', $where);

$orders = dbFetchAll($db,
    "SELECT o.*, c.name AS client_name, c.phone_number
     FROM orders o JOIN clients c ON c.id = o.client_id
     WHERE $whereStr ORDER BY o.created_at DESC LIMIT 100",
    $params
);
$statusCounts = dbFetchAll($db,
    "SELECT status, COUNT(*) as cnt FROM orders GROUP BY status"
);
$counts = [];
foreach ($statusCounts as $s) { $counts[$s['status']] = $s['cnt']; }

include __DIR__ . '/../includes/layout-admin.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">🛍️ Pedidos</div>
    <div class="page-subtitle">Gerenciamento de pedidos recebidos pelo bot</div>
  </div>
</div>

<!-- Filtros de Status -->
<div class="flex gap-2 mb-3" style="flex-wrap:wrap">
  <a href="?" class="btn <?= !$statusFilter ? 'btn-primary' : 'btn-outline' ?> btn-sm">
    Todos (<?= array_sum($counts) ?>)
  </a>
  <?php
  $statusList = ['confirmed'=>'Confirmados','preparing'=>'Preparando','out_for_delivery'=>'Saiu p/ entrega','delivered'=>'Entregues','cancelled'=>'Cancelados'];
  foreach ($statusList as $k => $v):
  ?>
  <a href="?status=<?= $k ?>" class="btn <?= $statusFilter === $k ? 'btn-primary' : 'btn-outline' ?> btn-sm">
    <?= $v ?> (<?= $counts[$k] ?? 0 ?>)
  </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>#</th><th>Cliente</th><th>Itens</th><th>Total</th><th>Bairro</th><th>Pagamento</th><th>Status</th><th>Data</th><th>Ações</th></tr>
      </thead>
      <tbody>
      <?php if (empty($orders)): ?>
        <tr><td colspan="9">
          <div class="empty-state">
            <div class="empty-icon">🛍️</div>
            <h3>Nenhum pedido encontrado</h3>
          </div>
        </td></tr>
      <?php else: foreach ($orders as $o): ?>
        <tr>
          <td><strong>#<?= $o['id'] ?></strong></td>
          <td>
            <div><?= htmlspecialchars($o['client_name'] ?: '—') ?></div>
            <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($o['phone_number']) ?></div>
          </td>
          <td>
            <?php
            $items = dbFetchAll($db, 'SELECT product_name, quantity FROM order_items WHERE order_id = ?', [$o['id']]);
            foreach ($items as $it): ?>
              <div style="font-size:12px"><?= $it['quantity'] ?>x <?= htmlspecialchars($it['product_name']) ?></div>
            <?php endforeach; ?>
          </td>
          <td><strong><?= formatCurrency($o['total']) ?></strong>
            <?php if ($o['freight_price'] > 0): ?>
              <div style="font-size:11px;color:var(--text-muted)">+<?= formatCurrency($o['freight_price']) ?> frete</div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($o['neighborhood'] ?: '—') ?></td>
          <td><?= htmlspecialchars($o['payment_method'] ?: '—') ?></td>
          <td><?= getStatusBadge($o['status']) ?></td>
          <td style="font-size:12px"><?= date('d/m H:i', strtotime($o['created_at'])) ?></td>
          <td>
            <select onchange="updateOrderStatus(<?= $o['id'] ?>, this.value)" class="form-select"
                    style="width:auto;padding:4px 8px;font-size:12px">
              <?php foreach ($statusList as $k => $v): ?>
                <option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
              <option value="cancelled" <?= $o['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelar</option>
            </select>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
async function updateOrderStatus(id, status) {
  const res = await api(`/api/orders.php?id=${id}`, 'POST', { action: 'update_status', status });
  if (res.success) toast('Status atualizado!', 'success');
  else toast(res.error || 'Erro', 'error');
}
</script>

<?php include __DIR__ . '/../includes/layout-admin-end.php'; ?>
