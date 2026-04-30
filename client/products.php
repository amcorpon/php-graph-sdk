<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireClient();

$activePage = 'products';
$pageTitle  = 'Produtos e Fretes';
$tab        = $_GET['tab'] ?? 'products';
$products   = dbFetchAll($db, 'SELECT * FROM products ORDER BY sort_order, name');
$freight    = dbFetchAll($db, 'SELECT * FROM freight ORDER BY neighborhood');

include __DIR__ . '/../includes/layout-client.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">📦 Produtos e Fretes</div>
    <div class="page-subtitle">Configure o cardápio e as taxas de entrega</div>
  </div>
</div>

<div class="tabs">
  <button class="tab-btn <?= $tab === 'products' ? 'active' : '' ?>" onclick="switchTab('products', event)">📦 Produtos</button>
  <button class="tab-btn <?= $tab === 'freight' ? 'active' : '' ?>"  onclick="switchTab('freight', event)">🚗 Fretes</button>
</div>

<!-- PRODUTOS -->
<div id="tab-products" style="display:<?= $tab === 'products' ? 'block' : 'none' ?>">
  <div class="flex-between mb-3">
    <p style="font-size:13px;color:var(--text-muted)"><?= count($products) ?> produtos cadastrados</p>
    <button onclick="openProductModal()" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Novo Produto</button>
  </div>
  <div class="card">
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Produto</th><th>Categoria</th><th>Preço</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
        <?php if (empty($products)): ?>
          <tr><td colspan="5">
            <div class="empty-state">
              <div class="empty-icon">📦</div>
              <h3>Nenhum produto ainda</h3>
              <p>Adicione seus produtos para que o bot possa receber pedidos</p>
            </div>
          </td></tr>
        <?php else: foreach ($products as $p): ?>
          <tr id="prod-<?= $p['id'] ?>">
            <td>
              <div style="font-weight:600"><?= htmlspecialchars($p['name']) ?></div>
              <?php if ($p['description']): ?><div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars(substr($p['description'],0,60)) ?></div><?php endif; ?>
            </td>
            <td><?= htmlspecialchars($p['category'] ?: '—') ?></td>
            <td><strong><?= formatCurrency($p['price']) ?></strong></td>
            <td><span class="badge <?= $p['is_active'] ? 'badge-success' : 'badge-secondary' ?>"><?= $p['is_active'] ? 'Ativo' : 'Inativo' ?></span></td>
            <td>
              <div class="flex gap-2">
                <button onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)" class="btn btn-outline btn-sm"><i class="fa-solid fa-pen"></i></button>
                <button onclick="toggleProduct(<?= $p['id'] ?>)" class="btn btn-outline btn-sm"><i class="fa-solid fa-eye<?= $p['is_active'] ? '-slash' : '' ?>"></i></button>
                <button onclick="deleteProduct(<?= $p['id'] ?>)" class="btn btn-outline btn-sm" style="color:var(--danger)"><i class="fa-solid fa-trash"></i></button>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- FRETES -->
<div id="tab-freight" style="display:<?= $tab === 'freight' ? 'block' : 'none' ?>">
  <div class="flex-between mb-3">
    <p style="font-size:13px;color:var(--text-muted)"><?= count($freight) ?> bairros cadastrados</p>
    <button onclick="openFreightModal()" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Novo Bairro</button>
  </div>
  <div class="card">
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Bairro</th><th>Cidade</th><th>Frete</th><th>Tempo</th><th>Ações</th></tr></thead>
        <tbody>
        <?php if (empty($freight)): ?>
          <tr><td colspan="5">
            <div class="empty-state">
              <div class="empty-icon">🚗</div>
              <h3>Nenhum bairro de entrega ainda</h3>
              <p>Adicione os bairros onde você entrega</p>
            </div>
          </td></tr>
        <?php else: foreach ($freight as $f): ?>
          <tr id="freight-<?= $f['id'] ?>">
            <td><strong><?= htmlspecialchars($f['neighborhood']) ?></strong></td>
            <td><?= htmlspecialchars($f['city'] ?: '—') ?></td>
            <td><?= $f['price'] == 0 ? '<span class="badge badge-success">Grátis</span>' : formatCurrency($f['price']) ?></td>
            <td><?= htmlspecialchars($f['delivery_time'] ?: '—') ?></td>
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
</div>

<!-- MODAL PRODUTO -->
<div id="product-modal" class="modal-backdrop" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="product-modal-title">Novo Produto</div>
      <button class="modal-close" onclick="closeModal('product-modal')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="product-id">
      <div class="form-group"><label class="form-label">Nome *</label><input type="text" id="product-name" class="form-control"></div>
      <div class="grid-2">
        <div class="form-group"><label class="form-label">Preço *</label><input type="number" id="product-price" class="form-control" step="0.01" min="0"></div>
        <div class="form-group"><label class="form-label">Categoria</label><input type="text" id="product-category" class="form-control" placeholder="Ex: Pizzas..."></div>
      </div>
      <div class="form-group"><label class="form-label">Descrição</label><textarea id="product-description" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer">
      <button onclick="closeModal('product-modal')" class="btn btn-outline">Cancelar</button>
      <button onclick="saveProduct()" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
    </div>
  </div>
</div>

<!-- MODAL FRETE -->
<div id="freight-modal" class="modal-backdrop" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="freight-modal-title">Novo Bairro</div>
      <button class="modal-close" onclick="closeModal('freight-modal')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="freight-id">
      <div class="form-group"><label class="form-label">Bairro *</label><input type="text" id="freight-neighborhood" class="form-control"></div>
      <div class="grid-2">
        <div class="form-group"><label class="form-label">Cidade</label><input type="text" id="freight-city" class="form-control"></div>
        <div class="form-group"><label class="form-label">Frete (R$) — 0=grátis</label><input type="number" id="freight-price" class="form-control" step="0.01" min="0" value="0"></div>
      </div>
      <div class="form-group"><label class="form-label">Tempo de entrega</label><input type="text" id="freight-time" class="form-control" placeholder="30-45 min"></div>
    </div>
    <div class="modal-footer">
      <button onclick="closeModal('freight-modal')" class="btn btn-outline">Cancelar</button>
      <button onclick="saveFreight()" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
    </div>
  </div>
</div>

<script>
function switchTab(tab, e) {
  document.getElementById('tab-products').style.display = tab === 'products' ? 'block' : 'none';
  document.getElementById('tab-freight').style.display  = tab === 'freight'  ? 'block' : 'none';
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  if (e) e.target.classList.add('active');
}

function openProductModal() {
  document.getElementById('product-modal-title').textContent = 'Novo Produto';
  document.getElementById('product-id').value = '';
  ['name','price','category','description'].forEach(f => { const el = document.getElementById('product-'+f); if(el) el.value=''; });
  openModal('product-modal');
}

function editProduct(p) {
  document.getElementById('product-modal-title').textContent = 'Editar Produto';
  document.getElementById('product-id').value = p.id;
  document.getElementById('product-name').value = p.name;
  document.getElementById('product-price').value = p.price;
  document.getElementById('product-category').value = p.category || '';
  document.getElementById('product-description').value = p.description || '';
  openModal('product-modal');
}

async function saveProduct() {
  const id = document.getElementById('product-id').value;
  const name = document.getElementById('product-name').value.trim();
  const price = parseFloat(document.getElementById('product-price').value);
  if (!name) { toast('Nome é obrigatório', 'error'); return; }
  if (isNaN(price) || price < 0) { toast('Preço inválido', 'error'); return; }
  const res = await api(id ? `/api/products.php?id=${id}` : '/api/products.php', 'POST', {
    action: id ? 'update' : 'create', name, price,
    description: document.getElementById('product-description').value,
    category: document.getElementById('product-category').value
  });
  if (res.success) { toast(res.message, 'success'); closeModal('product-modal'); setTimeout(() => location.reload(), 600); }
  else toast(res.error || 'Erro', 'error');
}

async function toggleProduct(id) {
  const res = await api(`/api/products.php?id=${id}`, 'POST', { action: 'toggle' });
  if (res.success) { toast('Status atualizado', 'success'); setTimeout(() => location.reload(), 500); }
}

async function deleteProduct(id) {
  if (!confirm('Remover produto?')) return;
  const res = await api(`/api/products.php?id=${id}`, 'POST', { action: 'delete' });
  if (res.success) { toast('Removido', 'success'); document.getElementById('prod-'+id)?.remove(); }
}

function openFreightModal() {
  document.getElementById('freight-modal-title').textContent = 'Novo Bairro';
  ['id','neighborhood','city','time'].forEach(f => { const el = document.getElementById('freight-'+f); if(el) el.value=''; });
  document.getElementById('freight-price').value = '0';
  openModal('freight-modal');
}

function editFreight(f) {
  document.getElementById('freight-modal-title').textContent = 'Editar Bairro';
  document.getElementById('freight-id').value = f.id;
  document.getElementById('freight-neighborhood').value = f.neighborhood;
  document.getElementById('freight-city').value = f.city || '';
  document.getElementById('freight-price').value = f.price;
  document.getElementById('freight-time').value = f.delivery_time || '';
  openModal('freight-modal');
}

async function saveFreight() {
  const id = document.getElementById('freight-id').value;
  const neighborhood = document.getElementById('freight-neighborhood').value.trim();
  if (!neighborhood) { toast('Bairro é obrigatório', 'error'); return; }
  const res = await api(id ? `/api/freight.php?id=${id}` : '/api/freight.php', 'POST', {
    action: id ? 'update' : 'create', neighborhood,
    city: document.getElementById('freight-city').value,
    price: parseFloat(document.getElementById('freight-price').value) || 0,
    delivery_time: document.getElementById('freight-time').value, is_active: 1
  });
  if (res.success) { toast(res.message, 'success'); closeModal('freight-modal'); setTimeout(() => location.reload(), 600); }
  else toast(res.error || 'Erro', 'error');
}

async function deleteFreight(id) {
  if (!confirm('Remover bairro?')) return;
  const res = await api(`/api/freight.php?id=${id}`, 'POST', { action: 'delete' });
  if (res.success) { toast('Removido', 'success'); document.getElementById('freight-'+id)?.remove(); }
}
</script>

<?php include __DIR__ . '/../includes/layout-client-end.php'; ?>
