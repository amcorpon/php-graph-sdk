<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$activePage = 'products';
$pageTitle  = 'Produtos';
$products   = dbFetchAll($db, 'SELECT * FROM products ORDER BY sort_order, name');

include __DIR__ . '/../includes/layout-admin.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">📦 Produtos</div>
    <div class="page-subtitle">Gerencie o catálogo de produtos do seu cardápio</div>
  </div>
  <button onclick="openProductModal()" class="btn btn-primary">
    <i class="fa-solid fa-plus"></i> Novo Produto
  </button>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Nome</th><th>Categoria</th><th>Preço</th><th>Estoque</th><th>Status</th><th>Ações</th>
        </tr>
      </thead>
      <tbody id="products-tbody">
      <?php if (empty($products)): ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <div class="empty-icon">📦</div>
            <h3>Nenhum produto cadastrado</h3>
            <p>Clique em "Novo Produto" para começar</p>
          </div>
        </td></tr>
      <?php else: foreach ($products as $p): ?>
        <tr id="product-row-<?= $p['id'] ?>">
          <td>#<?= $p['id'] ?></td>
          <td>
            <div style="font-weight:600"><?= htmlspecialchars($p['name']) ?></div>
            <?php if ($p['description']): ?>
              <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars(substr($p['description'],0,60)) ?>...</div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($p['category'] ?: '—') ?></td>
          <td><strong><?= formatCurrency($p['price']) ?></strong></td>
          <td><?= $p['stock'] !== null ? $p['stock'] : '∞' ?></td>
          <td>
            <span class="badge <?= $p['is_active'] ? 'badge-success' : 'badge-secondary' ?>">
              <?= $p['is_active'] ? 'Ativo' : 'Inativo' ?>
            </span>
          </td>
          <td>
            <div class="flex gap-2">
              <button onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-pen"></i>
              </button>
              <button onclick="toggleProduct(<?= $p['id'] ?>)" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-eye<?= $p['is_active'] ? '-slash' : '' ?>"></i>
              </button>
              <button onclick="deleteProduct(<?= $p['id'] ?>)" class="btn btn-outline btn-sm" style="color:var(--danger)">
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

<!-- MODAL -->
<div id="product-modal" class="modal-backdrop" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-title">Novo Produto</div>
      <button class="modal-close" onclick="closeModal('product-modal')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="product-id">
      <div class="form-group">
        <label class="form-label">Nome do Produto *</label>
        <input type="text" id="product-name" class="form-control" placeholder="Ex: Pizza Margherita">
      </div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Preço *</label>
          <input type="number" id="product-price" class="form-control" step="0.01" min="0" placeholder="0.00">
        </div>
        <div class="form-group">
          <label class="form-label">Categoria</label>
          <input type="text" id="product-category" class="form-control" placeholder="Ex: Pizzas, Bebidas...">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Descrição</label>
        <textarea id="product-description" class="form-control" rows="3" placeholder="Descrição que o bot vai mostrar..."></textarea>
      </div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Estoque <small>(deixe vazio para ilimitado)</small></label>
          <input type="number" id="product-stock" class="form-control" min="0" placeholder="Ilimitado">
        </div>
        <div class="form-group">
          <label class="form-label">Ordem de exibição</label>
          <input type="number" id="product-sort" class="form-control" value="0">
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button onclick="closeModal('product-modal')" class="btn btn-outline">Cancelar</button>
      <button onclick="saveProduct()" class="btn btn-primary">
        <i class="fa-solid fa-floppy-disk"></i> Salvar
      </button>
    </div>
  </div>
</div>

<script>
function openProductModal() {
  document.getElementById('modal-title').textContent = 'Novo Produto';
  document.getElementById('product-id').value = '';
  ['name','price','category','description','stock','sort'].forEach(f => {
    const el = document.getElementById('product-' + f);
    if (el) el.value = f === 'sort' ? '0' : '';
  });
  openModal('product-modal');
}

function editProduct(p) {
  document.getElementById('modal-title').textContent = 'Editar Produto';
  document.getElementById('product-id').value = p.id;
  document.getElementById('product-name').value = p.name;
  document.getElementById('product-price').value = p.price;
  document.getElementById('product-category').value = p.category || '';
  document.getElementById('product-description').value = p.description || '';
  document.getElementById('product-stock').value = p.stock ?? '';
  document.getElementById('product-sort').value = p.sort_order || 0;
  openModal('product-modal');
}

async function saveProduct() {
  const id   = document.getElementById('product-id').value;
  const name = document.getElementById('product-name').value.trim();
  const price = parseFloat(document.getElementById('product-price').value);
  if (!name) { toast('Nome é obrigatório', 'error'); return; }
  if (isNaN(price) || price < 0) { toast('Preço inválido', 'error'); return; }

  const data = {
    action: id ? 'update' : 'create',
    name, price,
    description: document.getElementById('product-description').value,
    category:    document.getElementById('product-category').value,
    stock:       document.getElementById('product-stock').value || null,
    sort_order:  parseInt(document.getElementById('product-sort').value) || 0
  };

  const url = id ? `/api/products.php?id=${id}` : '/api/products.php';
  const res = await api(url, 'POST', data);
  if (res.success) {
    toast(res.message, 'success');
    closeModal('product-modal');
    setTimeout(() => location.reload(), 600);
  } else {
    toast(res.error || 'Erro ao salvar', 'error');
  }
}

async function toggleProduct(id) {
  const res = await api(`/api/products.php?id=${id}`, 'POST', { action: 'toggle' });
  if (res.success) { toast('Status atualizado', 'success'); setTimeout(() => location.reload(), 500); }
}

async function deleteProduct(id) {
  if (!confirm('Remover este produto? Esta ação não pode ser desfeita.')) return;
  const res = await api(`/api/products.php?id=${id}`, 'POST', { action: 'delete' });
  if (res.success) {
    toast('Produto removido', 'success');
    document.getElementById('product-row-' + id)?.remove();
  } else { toast(res.error, 'error'); }
}
</script>

<?php include __DIR__ . '/../includes/layout-admin-end.php'; ?>
