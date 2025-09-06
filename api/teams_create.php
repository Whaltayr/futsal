<?php
declare(strict_types=1);
require_once __DIR__ . '/api/auth_check.php';
require_once __DIR__ . '/api/connection.php';
require_once __DIR__ . '/api/upload_helper.php';

$tournament_id = (int)($_POST['tournament_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$abbr = trim($_POST['abbreviation'] ?? '');
$city = trim($_POST['city'] ?? '');

if ($tournament_id<=0 || $name==='') { http_response_code(400); exit('Dados inválidos'); }

$upload = handle_image_upload($_FILES['logo'] ?? [], $_SERVER['DOCUMENT_ROOT'].'/futsal/futsal-pj/uploads/teams');
if (!$upload['ok']) { http_response_code(400); exit($upload['error']); }
$logo_url = $upload['path'] ?? '';

$stmt = $mysqli->prepare("INSERT INTO teams (tournament_id, name, abbreviation, city, logo_url) VALUES (?,?,?,?,?)");
$stmt->bind_param('issss', $tournament_id, $name, $abbr, $city, $logo_url);
$stmt->execute();

header('Location: /futsal-pj/admin/teams.php');
