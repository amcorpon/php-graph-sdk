<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$id     = (int)($_GET['id'] ?? 0);

if ($method === 'GET') {
    if ($id) {
        $order = dbFetchOne($db,
            'SELECT o.*, c.name AS client_name, c.phone_number FROM orders o
             JOIN clients c ON c.id = o.client_id WHERE o.id = ?', [$id]
        );
        if (!$order) jsonError('Pedido não encontrado', 404);
        $items = dbFetchAll($db, 'SELECT * FROM order_items WHERE order_id = ?', [$id]);
        jsonSuccess(['order' => $order, 'items' => $items]);
    }

    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $limit  = min((int)($_GET['limit'] ?? 50), 200);
    $offset = (int)($_GET['offset'] ?? 0);

    $where  = ['1=1'];
    $params = [];
    if ($status) { $where[] = 'o.status = ?'; $params[] = $status; }
    if ($search) {
        $where[] = '(c.name LIKE ? OR c.phone_number LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $whereStr = implode(' AND ', $where);

    $orders = dbFetchAll($db,
        "SELECT o.*, c.name AS client_name, c.phone_number
         FROM orders o JOIN clients c ON c.id = o.client_id
         WHERE $whereStr ORDER BY o.created_at DESC LIMIT ? OFFSET ?",
        array_merge($params, [$limit, $offset])
    );
    $total = dbFetchOne($db, "SELECT COUNT(*) as cnt FROM orders o JOIN clients c ON c.id = o.client_id WHERE $whereStr", $params);
    jsonSuccess(['orders' => $orders, 'total' => (int)$total['cnt']]);
}

if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'update_status') {
        if (!$id) jsonError('ID não informado');
        $allowed = ['pending','confirmed','preparing','out_for_delivery','delivered','cancelled'];
        if (!in_array($data['status'], $allowed)) jsonError('Status inválido');
        dbExecute($db, 'UPDATE orders SET status = ? WHERE id = ?', [$data['status'], $id]);
        jsonSuccess([], 'Status atualizado!');
    }
}
