<?php
/**
 * Trigger the Python processing pipeline for a project.
 * Downloads media, cuts videos, generates TTS audio.
 * Runs asynchronously in background.
 */
require_once '../db.php';
requireMethod('POST');

$data       = getInput();
$project_id = intval($data['project_id'] ?? 0);
if (!$project_id) jsonResponse(['error' => 'project_id required'], 400);

$project = getProject($project_id);
if (!$project) jsonResponse(['error' => 'Project not found'], 404);

// Gather all config for Python
$cfg = [
    'project_id'         => $project_id,
    'project_title'      => $project['title'],
    'theme'              => $project['theme'],
    'voice_provider'     => $project['voice_provider'],
    'voice_id'           => $project['voice_id'],
    'language_code'      => $project['language_code'],
    'claude_api_key'     => getConfig('claude_api_key'),
    'gemini_api_key'     => getConfig('gemini_api_key'),
    'google_tts_api_key' => getConfig('google_tts_api_key'),
    'elevenlabs_api_key' => getConfig('elevenlabs_api_key'),
    'ffmpeg_bin'         => getConfig('ffmpeg_bin', 'ffmpeg'),
    'sections'           => getProjectSections($project_id),
    'output_dir'         => projectDir($project_id, $project['title']),
];

// Write config JSON to temp file
$cfg_file = sys_get_temp_dir() . "/vsg_project_{$project_id}.json";
file_put_contents($cfg_file, json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$python  = getConfig('python_bin', 'python3');
$script  = escapeshellarg(dirname(__DIR__) . '/python/process_project.py');
$cfg_arg = escapeshellarg($cfg_file);
$log_file = dirname(__DIR__) . "/projects/project_{$project_id}.log";

// Run in background
$cmd = "$python $script $cfg_arg > " . escapeshellarg($log_file) . " 2>&1 &";
exec($cmd);

updateProjectStatus($project_id, 'processing');

jsonResponse([
    'success'  => true,
    'message'  => 'Processing started in background',
    'log_file' => "projects/project_{$project_id}.log",
]);
