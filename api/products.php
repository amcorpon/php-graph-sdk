<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdminLoggedIn() && !isClientLoggedIn()) jsonError('Não autorizado', 401);

$method = $_SERVER['REQUEST_METHOD'];
$id     = (int)($_GET['id'] ?? 0);

if ($method === 'GET') {
    if ($id) {
        $p = dbFetchOne($db, 'SELECT * FROM products WHERE id = ?', [$id]);
        jsonSuccess(['product' => $p]);
    }
    $products = dbFetchAll($db, 'SELECT * FROM products ORDER BY sort_order, name');
    jsonSuccess(['products' => $products]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? 'create';

    if ($action === 'create') {
        $name  = trim($data['name'] ?? '');
        $price = (float)($data['price'] ?? 0);
        if (empty($name)) jsonError('Nome do produto é obrigatório');
        if ($price < 0) jsonError('Preço inválido');

        $newId = dbInsert($db,
            'INSERT INTO products (name, price, description, category, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?)',
            [$name, $price, $data['description'] ?? '', $data['category'] ?? '', 1, (int)($data['sort_order'] ?? 0)]
        );
        jsonSuccess(['id' => $newId], 'Produto criado!');
    }

    if ($action === 'update') {
        if (!$id) jsonError('ID não informado');
        dbExecute($db,
            'UPDATE products SET name = ?, price = ?, description = ?, category = ?, is_active = ?, sort_order = ? WHERE id = ?',
            [trim($data['name']), (float)$data['price'], $data['description'] ?? '', $data['category'] ?? '',
             (int)($data['is_active'] ?? 1), (int)($data['sort_order'] ?? 0), $id]
        );
        jsonSuccess([], 'Produto atualizado!');
    }

    if ($action === 'delete') {
        if (!$id) jsonError('ID não informado');
        dbExecute($db, 'DELETE FROM products WHERE id = ?', [$id]);
        jsonSuccess([], 'Produto removido!');
    }

    if ($action === 'toggle') {
        if (!$id) jsonError('ID não informado');
        dbExecute($db, 'UPDATE products SET is_active = NOT is_active WHERE id = ?', [$id]);
        jsonSuccess([], 'Status atualizado!');
    }
}
