<?php
// Carregar .env se existir (sem depender de bibliotecas externas)
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if (!getenv($key)) putenv("$key=$value");
    }
}

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'whatsapp_bot';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

$db = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);

if (!$db) {
    http_response_code(500);
    die(json_encode(['error' => 'Erro de conexão com o banco de dados: ' . mysqli_connect_error()]));
}

mysqli_set_charset($db, 'utf8mb4');

function dbQuery($db, $sql, $params = [], $types = '') {
    if (empty($params)) {
        return mysqli_query($db, $sql);
    }
    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) return false;
    if (!empty($params)) {
        if (empty($types)) {
            $types = str_repeat('s', count($params));
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}

function dbFetchAll($db, $sql, $params = [], $types = '') {
    $result = dbQuery($db, $sql, $params, $types);
    if (!$result) return [];
    if ($result instanceof mysqli_stmt) {
        $res = mysqli_stmt_get_result($result);
        return mysqli_fetch_all($res, MYSQLI_ASSOC);
    }
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function dbFetchOne($db, $sql, $params = [], $types = '') {
    $rows = dbFetchAll($db, $sql, $params, $types);
    return $rows[0] ?? null;
}

function dbInsert($db, $sql, $params = [], $types = '') {
    $result = dbQuery($db, $sql, $params, $types);
    if (!$result) return false;
    return mysqli_insert_id($db);
}

function dbExecute($db, $sql, $params = [], $types = '') {
    $result = dbQuery($db, $sql, $params, $types);
    if (!$result) return false;
    return mysqli_affected_rows($db);
}

function dbEscape($db, $value) {
    return mysqli_real_escape_string($db, $value);
}
