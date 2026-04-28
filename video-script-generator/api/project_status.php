<?php
require_once '../db.php';

$project_id = intval($_GET['project_id'] ?? 0);
if (!$project_id) jsonResponse(['error' => 'project_id required'], 400);

$project  = getProject($project_id);
if (!$project) jsonResponse(['error' => 'Project not found'], 404);

$sections = getProjectSections($project_id);

// Compute progress
$total    = count($sections);
$done     = count(array_filter($sections, fn($s) => $s['status'] === 'done'));
$progress = $total > 0 ? round($done / $total * 100) : 0;

// List processed files
$dir     = projectDir($project_id, $project['title']);
$files   = [];
$proc_dir = $dir . '/processed';
if (is_dir($proc_dir)) {
    $entries = scandir($proc_dir);
    foreach ($entries as $f) {
        if ($f === '.' || $f === '..') continue;
        $files[] = [
            'name' => $f,
            'size' => filesize("$proc_dir/$f"),
            'url'  => "projects/" . basename($dir) . "/processed/$f",
        ];
    }
}

jsonResponse([
    'project'  => $project,
    'sections' => $sections,
    'progress' => $progress,
    'files'    => $files,
]);
