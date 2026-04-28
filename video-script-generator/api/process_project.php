<?php
/**
 * Queue a project for the local Python worker to process.
 * Sets status → 'queued'. The Python worker running on the user's machine
 * polls /api/worker/pending_projects.php and picks it up.
 */
require_once '../db.php';
requireMethod('POST');

$data       = getInput();
$project_id = intval($data['project_id'] ?? 0);
if (!$project_id) jsonResponse(['error' => 'project_id required'], 400);

$project = getProject($project_id);
if (!$project) jsonResponse(['error' => 'Project not found'], 404);

// Verify worker token is configured
$token = getConfig('worker_api_token');
if (!$token) {
    jsonResponse([
        'error' => 'Worker API token not configured. Go to Settings → Worker Setup and generate a token first.',
    ], 400);
}

// Reset section statuses so worker re-processes
$db = getDB();
mysqli_query($db, "UPDATE vsg_sections
    SET status='confirmed'
    WHERE project_id=$project_id
    AND section_type IN ('broll_image','broll_video')
    AND status IN ('downloading','processing','done','error')");

// Narration/overlay sections back to pending
mysqli_query($db, "UPDATE vsg_sections
    SET status='pending'
    WHERE project_id=$project_id
    AND section_type IN ('narration','text_overlay')");

// Clear old log
$log_file = VSG_ROOT . "/projects/project_{$project_id}.log";
if (is_dir(VSG_ROOT . '/projects')) {
    file_put_contents($log_file, '');
}

updateProjectStatus($project_id, 'queued');

$server_url  = getConfig('server_public_url', '');
$instructions = $server_url
    ? "Run on your local machine:\n  python3 worker.py --server $server_url"
    : "Configure Server Public URL in Settings, then run:\n  python3 worker.py --server https://yourserver.com/video-script-generator";

jsonResponse([
    'success'      => true,
    'status'       => 'queued',
    'project_id'   => $project_id,
    'instructions' => $instructions,
]);
