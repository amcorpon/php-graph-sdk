<?php
/**
 * Update project status from the local Python worker.
 * POST /api/worker/update_project.php
 * Body: { project_id, status, error_message? }
 */
require_once '_auth.php';
requireMethod('POST');

$data       = getInput();
$project_id = intval($data['project_id'] ?? 0);
$status     = $data['status'] ?? '';
$error      = $data['error_message'] ?? '';

if (!$project_id || !$status) jsonResponse(['error' => 'project_id and status required'], 400);

$allowed = ['processing','completed','error'];
if (!in_array($status, $allowed)) jsonResponse(['error' => 'Invalid status'], 400);

updateProjectStatus($project_id, $status, $error);
jsonResponse(['success' => true]);
