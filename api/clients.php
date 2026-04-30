<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$id     = (int)($_GET['id'] ?? 0);

if ($method === 'GET') {
    if ($id) {
        $client = dbFetchOne($db, 'SELECT * FROM clients WHERE id = ?', [$id]);
        if (!$client) jsonError('Cliente não encontrado', 404);
        $history = dbFetchAll($db,
            'SELECT * FROM conversations WHERE client_id = ? ORDER BY created_at DESC LIMIT 50', [$id]
        );
        $orders = dbFetchAll($db, 'SELECT * FROM orders WHERE client_id = ? ORDER BY created_at DESC LIMIT 10', [$id]);
        $appts  = dbFetchAll($db, 'SELECT * FROM appointments WHERE client_id = ? ORDER BY appointment_date DESC LIMIT 10', [$id]);
        jsonSuccess(['client' => $client, 'history' => $history, 'orders' => $orders, 'appointments' => $appts]);
    }

    $search = $_GET['search'] ?? '';
    $where  = '1=1';
    $params = [];
    if ($search) {
        $where = '(name LIKE ? OR phone_number LIKE ?)';
        $params = ["%$search%", "%$search%"];
    }
    $clients = dbFetchAll($db, "SELECT * FROM clients WHERE $where ORDER BY last_interaction DESC LIMIT 100", $params);
    jsonSuccess(['clients' => $clients]);
}

if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'block' && $id) {
        dbExecute($db, 'UPDATE clients SET blocked = 1 WHERE id = ?', [$id]);
        jsonSuccess([], 'Cliente bloqueado');
    }
    if ($action === 'unblock' && $id) {
        dbExecute($db, 'UPDATE clients SET blocked = 0 WHERE id = ?', [$id]);
        jsonSuccess([], 'Cliente desbloqueado');
    }
    if ($action === 'update_name' && $id) {
        dbExecute($db, 'UPDATE clients SET name = ? WHERE id = ?', [trim($data['name']), $id]);
        jsonSuccess([], 'Nome atualizado');
    }
    if ($action === 'update_notes' && $id) {
        dbExecute($db, 'UPDATE clients SET notes = ? WHERE id = ?', [trim($data['notes']), $id]);
        jsonSuccess([], 'Notas atualizadas');
    }
    if ($action === 'delete' && $id) {
        dbExecute($db, 'DELETE FROM clients WHERE id = ?', [$id]);
        jsonSuccess([], 'Cliente removido');
    }
    if ($action === 'reset_state' && $id) {
        dbExecute($db, "UPDATE conversation_state SET state = 'idle', context = '{}' WHERE client_id = ?", [$id]);
        jsonSuccess([], 'Estado da conversa resetado');
    }
}
