<?php
require_once '../db.php';
requireMethod('POST');

$data       = getInput();
$project_id = intval($data['project_id'] ?? 0);
if (!$project_id) jsonResponse(['error' => 'project_id required'], 400);

$project = getProject($project_id);
if (!$project) jsonResponse(['error' => 'Project not found'], 404);

$db  = getDB();
mysqli_query($db, "DELETE FROM vsg_projects WHERE id=$project_id");

// Remove project files
$dir = VSG_PROJECTS_DIR . "/$project_id" . '_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($project['title']));
if (is_dir($dir)) {
    array_map('unlink', glob("$dir/processed/*"));
    array_map('unlink', glob("$dir/raw/*"));
    @rmdir("$dir/processed");
    @rmdir("$dir/raw");
    @rmdir($dir);
}

jsonResponse(['success' => true]);
