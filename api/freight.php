<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdminLoggedIn() && !isClientLoggedIn()) jsonError('Não autorizado', 401);

$method = $_SERVER['REQUEST_METHOD'];
$id     = (int)($_GET['id'] ?? 0);

if ($method === 'GET') {
    $freight = dbFetchAll($db, 'SELECT * FROM freight ORDER BY neighborhood');
    jsonSuccess(['freight' => $freight]);
}

if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? 'create';

    if ($action === 'create') {
        $neighborhood = trim($data['neighborhood'] ?? '');
        $price        = (float)($data['price'] ?? 0);
        if (empty($neighborhood)) jsonError('Bairro é obrigatório');

        $newId = dbInsert($db,
            'INSERT INTO freight (neighborhood, city, price, delivery_time, is_active) VALUES (?, ?, ?, ?, 1)',
            [$neighborhood, $data['city'] ?? '', $price, $data['delivery_time'] ?? '']
        );
        jsonSuccess(['id' => $newId], 'Frete cadastrado!');
    }

    if ($action === 'update') {
        if (!$id) jsonError('ID não informado');
        dbExecute($db,
            'UPDATE freight SET neighborhood = ?, city = ?, price = ?, delivery_time = ?, is_active = ? WHERE id = ?',
            [trim($data['neighborhood']), $data['city'] ?? '', (float)$data['price'],
             $data['delivery_time'] ?? '', (int)($data['is_active'] ?? 1), $id]
        );
        jsonSuccess([], 'Frete atualizado!');
    }

    if ($action === 'delete') {
        if (!$id) jsonError('ID não informado');
        dbExecute($db, 'DELETE FROM freight WHERE id = ?', [$id]);
        jsonSuccess([], 'Frete removido!');
    }
}
