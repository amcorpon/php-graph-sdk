<?php
/**
 * Python worker streams log lines to the server so the web UI can show progress.
 * POST /api/worker/log.php
 * Body: { project_id, message, level? }
 */
require_once '_auth.php';
requireMethod('POST');

$data       = getInput();
$project_id = intval($data['project_id'] ?? 0);
$message    = trim($data['message'] ?? '');
$level      = $data['level'] ?? 'INFO';

if (!$project_id || !$message) jsonResponse(['error' => 'project_id and message required'], 400);

$log_file = VSG_ROOT . "/projects/project_{$project_id}.log";

// Ensure projects dir exists
if (!is_dir(VSG_PROJECTS_DIR)) mkdir(VSG_PROJECTS_DIR, 0755, true);

$line = '[' . date('H:i:s') . "] $level: $message\n";
file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);

jsonResponse(['success' => true]);
