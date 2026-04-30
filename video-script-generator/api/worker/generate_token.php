<?php
/**
 * Generate or regenerate the worker API token.
 * POST /api/worker/generate_token.php (no auth required — only called from web UI)
 */
require_once '../../db.php';
requireMethod('POST');

$token = bin2hex(random_bytes(32)); // 64 char hex token
setConfig('worker_api_token', $token);
jsonResponse(['success' => true, 'token' => $token]);
