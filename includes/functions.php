<?php
function formatCurrency($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function formatDate($date) {
    if (!$date) return '';
    $d = new DateTime($date);
    $days = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
    $months = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
    return $days[$d->format('w')] . ', ' . $d->format('d') . ' de ' . $months[$d->format('n')-1] . ' de ' . $d->format('Y');
}

function formatTime($time) {
    return substr($time, 0, 5);
}

function sanitize($value) {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

function getStatusBadge($status) {
    $badges = [
        'pending'          => ['class' => 'warning', 'label' => 'Pendente'],
        'confirmed'        => ['class' => 'info',    'label' => 'Confirmado'],
        'preparing'        => ['class' => 'primary',  'label' => 'Preparando'],
        'out_for_delivery' => ['class' => 'info',    'label' => 'Saiu p/ entrega'],
        'delivered'        => ['class' => 'success', 'label' => 'Entregue'],
        'cancelled'        => ['class' => 'danger',  'label' => 'Cancelado'],
        'completed'        => ['class' => 'success', 'label' => 'Concluído'],
        'no_show'          => ['class' => 'secondary','label' => 'Não compareceu'],
    ];
    $b = $badges[$status] ?? ['class' => 'secondary', 'label' => $status];
    return '<span class="badge bg-' . $b['class'] . '">' . $b['label'] . '</span>';
}

function getBotStatus($db) {
    return dbFetchOne($db, 'SELECT * FROM bot_status LIMIT 1');
}

function getConfig($db) {
    return dbFetchOne($db, 'SELECT * FROM bot_config LIMIT 1');
}

function timeAgo($datetime) {
    if (!$datetime) return 'nunca';
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);
    if ($diff->days > 0) return $diff->days . 'd atrás';
    if ($diff->h > 0) return $diff->h . 'h atrás';
    if ($diff->i > 0) return $diff->i . 'min atrás';
    return 'agora';
}

function logSystem($db, $level, $source, $message, $context = null) {
    dbInsert($db,
        'INSERT INTO system_logs (level, source, message, context) VALUES (?, ?, ?, ?)',
        [$level, $source, $message, $context ? json_encode($context) : null]
    );
}

function getDayName($dayOfWeek) {
    $days = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
    return $days[$dayOfWeek] ?? '';
}
