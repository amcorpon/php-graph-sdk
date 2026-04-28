<?php
require_once '../db.php';

$db  = getDB();
$res = mysqli_query($db, "SELECT p.*,
    (SELECT COUNT(*) FROM vsg_sections s WHERE s.project_id=p.id) AS total_sections,
    (SELECT COUNT(*) FROM vsg_sections s WHERE s.project_id=p.id AND s.status='done') AS done_sections
    FROM vsg_projects p ORDER BY p.created_at DESC LIMIT 50");

$projects = [];
while ($row = mysqli_fetch_assoc($res)) $projects[] = $row;

jsonResponse(['projects' => $projects]);
