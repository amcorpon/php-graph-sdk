<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAdmin() {
    if (empty($_SESSION['admin_id'])) {
        header('Location: /admin/login.php');
        exit;
    }
}

function requireClient() {
    if (empty($_SESSION['client_id'])) {
        header('Location: /client/login.php');
        exit;
    }
}

function isAdminLoggedIn() {
    return !empty($_SESSION['admin_id']);
}

function isClientLoggedIn() {
    return !empty($_SESSION['client_id']);
}

function adminLogin($db, $username, $password) {
    $user = dbFetchOne($db, 'SELECT * FROM admin_users WHERE username = ?', [$username]);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_role'] = $user['role'];
        dbExecute($db, 'UPDATE admin_users SET last_login = NOW() WHERE id = ?', [$user['id']]);
        return true;
    }
    return false;
}

function clientLogin($db, $username, $password) {
    $user = dbFetchOne($db, 'SELECT * FROM client_users WHERE username = ?', [$username]);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['client_id'] = $user['id'];
        $_SESSION['client_username'] = $user['username'];
        $_SESSION['client_business'] = $user['business_name'];
        dbExecute($db, 'UPDATE client_users SET last_login = NOW() WHERE id = ?', [$user['id']]);
        return true;
    }
    return false;
}

function logout() {
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError($message, $code = 400) {
    jsonResponse(['success' => false, 'error' => $message], $code);
}

function jsonSuccess($data = [], $message = '') {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}
