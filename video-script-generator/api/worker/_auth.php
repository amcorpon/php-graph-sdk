<?php
/**
 * Worker authentication middleware.
 * All /api/worker/ endpoints must include this file at the top.
 * Python sends: Authorization: Bearer <worker_api_token>
 */
require_once __DIR__ . '/../../db.php';

function requireWorkerAuth(): void {
    $stored_token = getConfig('worker_api_token');

    if (!$stored_token) {
        jsonResponse(['error' => 'Worker API token not configured on server. Go to Settings.'], 403);
    }

    $header = $_SERVER['HTTP_AUTHORIZATION']
           ?? $_SERVER['HTTP_X_API_TOKEN']
           ?? '';

    // Accept "Bearer <token>" or raw token
    $sent_token = str_starts_with($header, 'Bearer ')
                ? substr($header, 7)
                : $header;

    if (!hash_equals($stored_token, trim($sent_token))) {
        jsonResponse(['error' => 'Invalid worker token'], 401);
    }
}

requireWorkerAuth();
