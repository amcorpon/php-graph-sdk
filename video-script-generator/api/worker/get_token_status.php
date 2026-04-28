<?php
require_once '../../db.php';
$token = getConfig('worker_api_token');
jsonResponse(['configured' => !empty($token)]);
