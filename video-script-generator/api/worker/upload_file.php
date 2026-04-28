<?php
/**
 * Receive a processed file from the local Python worker and save it on the server.
 * POST /api/worker/upload_file.php (multipart/form-data)
 * Fields: project_id (int), section_id (int), file_field ('media'|'audio'), file (binary)
 *
 * Returns: { success, url, file_path, file_name }
 */
require_once '_auth.php';
requireMethod('POST');

$project_id = intval($_POST['project_id'] ?? 0);
$section_id = intval($_POST['section_id'] ?? 0);
$file_field = $_POST['file_field'] ?? 'media'; // 'media' or 'audio'

if (!$project_id || !$section_id) jsonResponse(['error' => 'project_id and section_id required'], 400);

$project = getProject($project_id);
if (!$project) jsonResponse(['error' => 'Project not found'], 404);

if (empty($_FILES['file'])) jsonResponse(['error' => 'No file uploaded'], 400);

$upload    = $_FILES['file'];
$orig_name = basename($upload['name']);

// Whitelist extensions
$ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
$allowed_ext = ['mp4','mp3','jpg','jpeg','png','webp','txt','wav'];
if (!in_array($ext, $allowed_ext)) jsonResponse(['error' => "Extension .$ext not allowed"], 400);

// Size limit: 500 MB
if ($upload['size'] > 500 * 1024 * 1024) jsonResponse(['error' => 'File too large (max 500MB)'], 400);

if ($upload['error'] !== UPLOAD_ERR_OK) jsonResponse(['error' => 'Upload error: ' . $upload['error']], 500);

$dir        = projectDir($project_id, $project['title']);
$proc_dir   = $dir . '/processed';
$dest       = $proc_dir . '/' . $orig_name;

if (!move_uploaded_file($upload['tmp_name'], $dest)) {
    jsonResponse(['error' => 'Failed to move uploaded file'], 500);
}

// Update section in DB
$col = $file_field === 'audio' ? 'audio_file' : 'file_path';
updateSectionStatus($section_id, 'done', [
    $col         => $dest,
    'file_name'  => $orig_name,
    'file_path'  => $dest,
]);

// Build public URL (relative to webroot)
$webroot    = VSG_ROOT;
$public_path = ltrim(str_replace($webroot, '', $dest), '/');
$base_url   = rtrim(getConfig('server_public_url', ''), '/');
$url        = $base_url ? "$base_url/$public_path" : $public_path;

jsonResponse([
    'success'   => true,
    'file_name' => $orig_name,
    'file_path' => $dest,
    'url'       => $url,
]);
