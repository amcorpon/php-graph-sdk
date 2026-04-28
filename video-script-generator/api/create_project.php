<?php
require_once '../db.php';
requireMethod('POST');

$data = getInput();

$theme          = trim($data['theme']          ?? '');
$duration       = intval($data['duration_minutes'] ?? 5);
$ai_provider    = $data['ai_provider']    ?? 'claude';
$voice_provider = $data['voice_provider'] ?? 'google';
$voice_id       = $data['voice_id']       ?? 'en-US-Neural2-F';
$language_code  = $data['language_code']  ?? 'en-US';
$claude_model   = getConfig('claude_model', 'claude-opus-4-7');

if (!$theme) jsonResponse(['error' => 'Theme is required'], 400);

$valid_ai    = ['claude','gemini','both'];
$valid_voice = ['google','elevenlabs'];
if (!in_array($ai_provider, $valid_ai))    $ai_provider    = 'claude';
if (!in_array($voice_provider, $valid_voice)) $voice_provider = 'google';

// Sanitize
$db    = getDB();
$t     = mysqli_real_escape_string($db, $theme);
$dur   = max(1, min(60, $duration));
$ai    = mysqli_real_escape_string($db, $ai_provider);
$vprov = mysqli_real_escape_string($db, $voice_provider);
$vid   = mysqli_real_escape_string($db, $voice_id);
$lang  = mysqli_real_escape_string($db, $language_code);
$cmod  = mysqli_real_escape_string($db, $claude_model);

// Generate a title from theme (AI will refine during script gen)
$title = ucwords($theme);
if (strlen($title) > 100) $title = substr($title, 0, 97) . '...';
$title_esc = mysqli_real_escape_string($db, $title);

mysqli_query($db, "INSERT INTO vsg_projects
    (title, theme, duration_minutes, ai_provider, claude_model, voice_provider, voice_id, language_code, status)
    VALUES ('$title_esc','$t',$dur,'$ai','$cmod','$vprov','$vid','$lang','draft')");

$project_id = mysqli_insert_id($db);
if (!$project_id) jsonResponse(['error' => 'Failed to create project'], 500);

jsonResponse(['success' => true, 'project_id' => $project_id, 'title' => $title]);
