<?php
/**
 * Returns full project data + sections + API keys for local Python worker.
 * GET /api/worker/get_project.php?project_id=X
 *
 * Python needs the API keys to:
 *   - Call Gemini for video timestamp analysis
 *   - Call Google TTS / ElevenLabs for narration audio
 */
require_once '_auth.php';

$project_id = intval($_GET['project_id'] ?? 0);
if (!$project_id) jsonResponse(['error' => 'project_id required'], 400);

$project = getProject($project_id);
if (!$project) jsonResponse(['error' => 'Project not found'], 404);

$sections = getProjectSections($project_id);

// Include API keys + project voice settings for the worker (sent over HTTPS)
$worker_config = [
    'gemini_api_key'     => getConfig('gemini_api_key'),
    'google_tts_api_key' => getConfig('google_tts_api_key'),
    'elevenlabs_api_key' => getConfig('elevenlabs_api_key'),
    'ffmpeg_bin'         => getConfig('ffmpeg_bin', 'ffmpeg'),
    // Voice settings come from the project
    'voice_provider'     => $project['voice_provider'],
    'voice_id'           => $project['voice_id'],
    'language_code'      => $project['language_code'],
];

// Mark project as 'processing' so other workers skip it
updateProjectStatus($project_id, 'processing');

jsonResponse([
    'project'       => $project,
    'sections'      => $sections,
    'worker_config' => $worker_config,
]);
