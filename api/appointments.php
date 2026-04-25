<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdminLoggedIn() && !isClientLoggedIn()) jsonError('Não autorizado', 401);

$method = $_SERVER['REQUEST_METHOD'];
$id     = (int)($_GET['id'] ?? 0);

// ── GET: listar / detalhar ────────────────────────────────────────────
if ($method === 'GET') {
    $type = $_GET['type'] ?? 'list';

    if ($type === 'services') {
        $services = dbFetchAll($db, 'SELECT * FROM services ORDER BY sort_order, name');
        jsonSuccess(['services' => $services]);
    }

    if ($type === 'available_slots') {
        $date = $_GET['date'] ?? date('Y-m-d');
        $dayOfWeek = (int)date('w', strtotime($date));
        $hours = dbFetchOne($db, 'SELECT * FROM business_hours WHERE day_of_week = ?', [$dayOfWeek]);
        if (!$hours || !$hours['is_open']) {
            jsonSuccess(['slots' => [], 'message' => 'Fechado neste dia']);
        }
        $booked = dbFetchAll($db,
            "SELECT appointment_time, duration_minutes FROM appointments WHERE appointment_date = ? AND status != 'cancelled'",
            [$date]
        );
        $slots = generateSlots($hours['open_time'], $hours['close_time'], 60, $booked);
        jsonSuccess(['slots' => $slots]);
    }

    if ($id) {
        $appt = dbFetchOne($db,
            'SELECT a.*, c.name AS client_name, c.phone_number FROM appointments a
             JOIN clients c ON c.id = a.client_id WHERE a.id = ?', [$id]
        );
        jsonSuccess(['appointment' => $appt]);
    }

    $month  = $_GET['month']  ?? date('Y-m');
    $status = $_GET['status'] ?? '';
    $where  = ['1=1'];
    $params = [];
    if ($month) { $where[] = "DATE_FORMAT(a.appointment_date, '%Y-%m') = ?"; $params[] = $month; }
    if ($status) { $where[] = 'a.status = ?'; $params[] = $status; }
    $whereStr = implode(' AND ', $where);

    $appointments = dbFetchAll($db,
        "SELECT a.*, c.name AS client_name, c.phone_number
         FROM appointments a JOIN clients c ON c.id = a.client_id
         WHERE $whereStr ORDER BY a.appointment_date, a.appointment_time",
        $params
    );
    jsonSuccess(['appointments' => $appointments]);
}

// ── POST: criar / atualizar ───────────────────────────────────────────
if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'save_service') {
        $name = trim($data['name'] ?? '');
        if (empty($name)) jsonError('Nome do serviço é obrigatório');
        if (!empty($data['id'])) {
            dbExecute($db,
                'UPDATE services SET name = ?, duration_minutes = ?, price = ?, description = ?, is_active = ? WHERE id = ?',
                [$name, (int)($data['duration_minutes'] ?? 60), $data['price'] ?: null, $data['description'] ?? '', (int)($data['is_active'] ?? 1), (int)$data['id']]
            );
            jsonSuccess([], 'Serviço atualizado!');
        } else {
            $newId = dbInsert($db,
                'INSERT INTO services (name, duration_minutes, price, description, is_active) VALUES (?, ?, ?, ?, 1)',
                [$name, (int)($data['duration_minutes'] ?? 60), $data['price'] ?: null, $data['description'] ?? '']
            );
            jsonSuccess(['id' => $newId], 'Serviço criado!');
        }
    }

    if ($action === 'delete_service') {
        dbExecute($db, 'DELETE FROM services WHERE id = ?', [(int)$data['id']]);
        jsonSuccess([], 'Serviço removido!');
    }

    if ($action === 'update_status') {
        if (!$id) jsonError('ID não informado');
        $allowed = ['confirmed','cancelled','completed','no_show'];
        if (!in_array($data['status'], $allowed)) jsonError('Status inválido');
        dbExecute($db, 'UPDATE appointments SET status = ? WHERE id = ?', [$data['status'], $id]);
        jsonSuccess([], 'Status atualizado!');
    }
}

function generateSlots($openTime, $closeTime, $interval, $booked) {
    $slots  = [];
    $start  = strtotime("2000-01-01 $openTime");
    $end    = strtotime("2000-01-01 $closeTime");
    $intSec = $interval * 60;

    for ($t = $start; $t + $intSec <= $end; $t += $intSec) {
        $timeStr = date('H:i:s', $t);
        $slotEnd = date('H:i:s', $t + $intSec);
        $conflict = false;
        foreach ($booked as $b) {
            $bStart = $b['appointment_time'];
            $bEnd   = addMinutes($b['appointment_time'], $b['duration_minutes']);
            if ($timeStr < $bEnd && $slotEnd > $bStart) { $conflict = true; break; }
        }
        if (!$conflict) $slots[] = substr($timeStr, 0, 5);
    }
    return $slots;
}

function addMinutes($time, $minutes) {
    $t = strtotime("2000-01-01 $time") + $minutes * 60;
    return date('H:i:s', $t);
}
