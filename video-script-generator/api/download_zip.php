<?php
/**
 * Package all processed files for a project into a ZIP and stream it.
 */
require_once '../db.php';

$project_id = intval($_GET['project_id'] ?? 0);
if (!$project_id) { http_response_code(400); exit('project_id required'); }

$project = getProject($project_id);
if (!$project) { http_response_code(404); exit('Project not found'); }

$dir       = projectDir($project_id, $project['title']);
$proc_dir  = $dir . '/processed';

if (!is_dir($proc_dir)) { http_response_code(404); exit('No files yet'); }

$files = array_filter(scandir($proc_dir), fn($f) => !in_array($f, ['.','..']));

if (!$files) { http_response_code(404); exit('No processed files'); }

$zip_name = preg_replace('/[^a-z0-9_]+/', '_', strtolower($project['title'])) . '_videoscript.zip';
$zip_path = sys_get_temp_dir() . '/' . $zip_name;

$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500); exit('Could not create ZIP');
}

foreach ($files as $file) {
    $zip->addFile("$proc_dir/$file", $file);
}

// Include script JSON
$script_json = $dir . '/script.json';
if (file_exists($script_json)) $zip->addFile($script_json, 'script.json');

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_name . '"');
header('Content-Length: ' . filesize($zip_path));
header('Cache-Control: no-cache');
readfile($zip_path);
unlink($zip_path);
exit;
