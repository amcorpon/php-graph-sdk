<?php
require_once '../db.php';

$project_id = intval($_GET['project_id'] ?? 0);
if (!$project_id) jsonResponse(['error' => 'project_id required'], 400);

$log_file = VSG_ROOT . "/projects/project_{$project_id}.log";
$content  = file_exists($log_file) ? file_get_contents($log_file) : '(no log yet)';

jsonResponse(['log' => $content]);
