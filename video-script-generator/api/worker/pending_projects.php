<?php
/**
 * Returns projects queued for local Python processing.
 * Python worker polls this endpoint to find work.
 * GET /api/worker/pending_projects.php
 */
require_once '_auth.php';

$db  = getDB();
$res = mysqli_query($db,
    "SELECT id, title, theme, duration_minutes, ai_provider, voice_provider,
            voice_id, language_code, status, updated_at
     FROM vsg_projects
     WHERE status = 'queued'
     ORDER BY updated_at ASC
     LIMIT 10"
);

$projects = [];
while ($row = mysqli_fetch_assoc($res)) $projects[] = $row;

jsonResponse(['projects' => $projects, 'count' => count($projects)]);
