<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/upload_helper.php';

$id = (int)($_POST['id'] ?? 0);
$tournament_id = (int)($_POST['tournament_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$abbr = trim($_POST['abbreviation'] ?? '');
$city = trim($_POST['city'] ?? '');

if ($id<=0 || $tournament_id<=0 || $name==='') { http_response_code(400); exit('Dados inválidos'); }

// obter logo atual
$stmt = $mysqli->prepare("SELECT logo_url FROM teams WHERE id=?");
$stmt->bind_param('i',$id);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();
$current_logo = $current['logo_url'] ?? '';

// se enviou novo ficheiro, substitui
$new_logo_url = $current_logo;
if (!empty($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $up = handle_image_upload($_FILES['logo'], $_SERVER['DOCUMENT_ROOT'].'/futsal-pj/uploads/teams');
    if (!$up['ok']) { http_response_code(400); exit($up['error']); }
    $new_logo_url = $up['path'] ?? $current_logo;
    // opcional: apagar antigo ficheiro do disco se quiseres
    // if ($current_logo && file_exists($_SERVER['DOCUMENT_ROOT'].$current_logo)) { @unlink($_SERVER['DOCUMENT_ROOT'].$current_logo); }
}

$stmt = $mysqli->prepare("UPDATE teams SET tournament_id=?, name=?, abbreviation=?, city=?, logo_url=? WHERE id=?");
$stmt->bind_param('issssi', $tournament_id, $name, $abbr, $city, $new_logo_url, $id);
$stmt->execute();

header('Location: /futsal-pj/admin/teams.php');
