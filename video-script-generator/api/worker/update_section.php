<?php
/**
 * Update section status and metadata from the local Python worker.
 * POST /api/worker/update_section.php
 * Body JSON: { section_id, status, file_name, cut_start, cut_end, error_message, ... }
 */
require_once '_auth.php';
requireMethod('POST');

$data   = getInput();
$sec_id = intval($data['section_id'] ?? 0);
$status = $data['status'] ?? '';

if (!$sec_id || !$status) jsonResponse(['error' => 'section_id and status required'], 400);

$allowed_statuses = ['pending','downloading','processing','done','error'];
if (!in_array($status, $allowed_statuses)) jsonResponse(['error' => 'Invalid status'], 400);

$extra = [];
foreach (['file_name','cut_start','cut_end','error_message','audio_file','media_source'] as $k) {
    if (isset($data[$k])) $extra[$k] = $data[$k];
}

// If worker uploads a file_name, generate the public path
if (!empty($data['file_name'])) {
    $db      = getDB();
    $res     = mysqli_query($db, "SELECT project_id FROM vsg_sections WHERE id=$sec_id LIMIT 1");
    $row     = mysqli_fetch_assoc($res);
    if ($row) {
        $project = getProject(intval($row['project_id']));
        if ($project) {
            $dir           = projectDir(intval($row['project_id']), $project['title']);
            $extra['file_path'] = $dir . '/processed/' . $data['file_name'];
        }
    }
}

updateSectionStatus($sec_id, $status, $extra);
jsonResponse(['success' => true]);
