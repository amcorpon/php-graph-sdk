<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isAdminLoggedIn() && !isClientLoggedIn()) jsonError('Não autorizado', 401);
    $config = dbFetchOne($db, 'SELECT * FROM bot_config LIMIT 1');
    $hours  = dbFetchAll($db, 'SELECT * FROM business_hours ORDER BY day_of_week');
    jsonSuccess(['config' => $config, 'business_hours' => $hours]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true);

    $fields = ['bot_name','owner_number','bot_number','bot_mode','response_length','use_emojis','persona',
               'response_limit','notify_owner','owner_notification_template','active_ai','ai_sequence',
               'business_hours_enabled','out_of_hours_message','welcome_message','return_message',
               'max_session_idle','ask_name_on_first_contact'];

    $set = [];
    $params = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $data)) {
            $set[] = "`$f` = ?";
            $params[] = is_array($data[$f]) ? json_encode($data[$f]) : $data[$f];
        }
    }

    if (empty($set)) jsonError('Nenhum campo para atualizar');

    $params[] = null; // placeholder for WHERE
    $sql = 'UPDATE bot_config SET ' . implode(', ', $set) . ' WHERE id = (SELECT id FROM (SELECT id FROM bot_config LIMIT 1) t)';
    // Simpler approach
    $existing = dbFetchOne($db, 'SELECT id FROM bot_config LIMIT 1');
    if ($existing) {
        array_pop($params);
        $params[] = $existing['id'];
        dbExecute($db, 'UPDATE bot_config SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
    }

    // Salvar horários de funcionamento
    if (!empty($data['business_hours']) && is_array($data['business_hours'])) {
        foreach ($data['business_hours'] as $bh) {
            dbExecute($db,
                'UPDATE business_hours SET open_time = ?, close_time = ?, is_open = ? WHERE day_of_week = ?',
                [$bh['open_time'], $bh['close_time'], (int)$bh['is_open'], (int)$bh['day_of_week']]
            );
        }
    }

    logSystem($db, 'info', 'config_api', 'Configurações atualizadas por: ' . ($_SESSION['admin_username'] ?? 'admin'));
    jsonSuccess([], 'Configurações salvas com sucesso!');
}
