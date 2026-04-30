<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $keys = dbFetchAll($db, 'SELECT id, provider, model, is_active, credits_exhausted, last_error, last_used,
        CASE WHEN LENGTH(api_key) > 8 THEN CONCAT(LEFT(api_key,4),"...") ELSE "***" END AS api_key_preview
        FROM ai_keys');
    jsonSuccess(['keys' => $keys]);
}

if ($method === 'POST') {
    $data     = json_decode(file_get_contents('php://input'), true);
    $provider = $data['provider'] ?? '';
    $apiKey   = trim($data['api_key'] ?? '');
    $model    = trim($data['model'] ?? '');
    $isActive = (int)($data['is_active'] ?? 1);

    $allowed = ['claude', 'gemini', 'gpt', 'groq'];
    if (!in_array($provider, $allowed)) jsonError('Provedor inválido');
    if (empty($apiKey)) jsonError('Chave de API é obrigatória');

    $existing = dbFetchOne($db, 'SELECT id FROM ai_keys WHERE provider = ?', [$provider]);
    if ($existing) {
        dbExecute($db,
            'UPDATE ai_keys SET api_key = ?, model = ?, is_active = ?, credits_exhausted = 0, last_error = NULL WHERE provider = ?',
            [$apiKey, $model, $isActive, $provider]
        );
    } else {
        dbInsert($db,
            'INSERT INTO ai_keys (provider, api_key, model, is_active) VALUES (?, ?, ?, ?)',
            [$provider, $apiKey, $model, $isActive]
        );
    }
    jsonSuccess([], "Chave da $provider salva com sucesso!");
}

if ($method === 'DELETE') {
    $provider = $_GET['provider'] ?? '';
    if (!in_array($provider, ['claude','gemini','gpt','groq'])) jsonError('Provedor inválido');
    dbExecute($db, 'DELETE FROM ai_keys WHERE provider = ?', [$provider]);
    jsonSuccess([], 'Chave removida!');
}
