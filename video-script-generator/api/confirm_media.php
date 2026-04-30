<?php
/**
 * Confirm a media selection for a section.
 */
require_once '../db.php';
requireMethod('POST');

$data   = getInput();
$sec_id = intval($data['section_id'] ?? 0);
$idx    = intval($data['option_index'] ?? 0);

if (!$sec_id) jsonResponse(['error' => 'section_id required'], 400);

$db     = getDB();
$res    = mysqli_query($db, "SELECT * FROM vsg_sections WHERE id=$sec_id LIMIT 1");
$sec    = mysqli_fetch_assoc($res);
if (!$sec) jsonResponse(['error' => 'Section not found'], 404);

$options = json_decode($sec['media_options'] ?? '[]', true);
if (!isset($options[$idx])) jsonResponse(['error' => 'Invalid option index'], 400);

$chosen = $options[$idx];

updateSectionStatus($sec_id, 'confirmed', [
    'selected_option'  => $idx,
    'media_url'        => $chosen['url']    ?? '',
    'media_thumb'      => $chosen['thumb']  ?? '',
    'media_pexels_id'  => (string)($chosen['id'] ?? ''),
    'media_source'     => $chosen['source'] ?? 'pexels',
]);

// Check if all broll sections for this project are confirmed
$project_id = intval($sec['project_id']);
$total  = mysqli_fetch_assoc(mysqli_query($db, "SELECT COUNT(*) AS c FROM vsg_sections WHERE project_id=$project_id AND section_type IN ('broll_image','broll_video')"));
$done   = mysqli_fetch_assoc(mysqli_query($db, "SELECT COUNT(*) AS c FROM vsg_sections WHERE project_id=$project_id AND section_type IN ('broll_image','broll_video') AND status='confirmed'"));

if ($total['c'] > 0 && $total['c'] == $done['c']) {
    updateProjectStatus($project_id, 'media_ready');
}

jsonResponse(['success' => true, 'chosen' => $chosen]);
