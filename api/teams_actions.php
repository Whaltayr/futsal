<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/upload_helper.php';

$action = $_POST['action'] ?? '';
if ($action === '') {
    http_response_code(400);
    exit('Ação não especificada');
}

switch ($action) {
    case 'create':
        $tournament_id = (int)($_POST['tournament_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $abbr = trim($_POST['abbreviation'] ?? '');
        $city = trim($_POST['city'] ?? '');

        if ($tournament_id<=0 || $name==='') { exit('Dados inválidos'); }

        $upload = handle_image_upload($_FILES['logo'] ?? [], $_SERVER['DOCUMENT_ROOT'].'/futsal/futsal-pj/uploads/teams');
        if (!$upload['ok']) { exit($upload['error']); }
        $logo_url = $upload['path'] ?? '';

        $stmt = $mysqli->prepare("INSERT INTO teams (tournament_id,name,abbreviation,city,logo_url) VALUES (?,?,?,?,?)");
        $stmt->bind_param('issss',$tournament_id,$name,$abbr,$city,$logo_url);
        $stmt->execute();
        break;

    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        $tournament_id = (int)($_POST['tournament_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $abbr = trim($_POST['abbreviation'] ?? '');
        $city = trim($_POST['city'] ?? '');
        if ($id<=0 || $tournament_id<=0 || $name==='') { exit('Dados inválidos'); }

        // logo atual
        $stmt = $mysqli->prepare("SELECT logo_url FROM teams WHERE id=?");
        $stmt->bind_param('i',$id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $current_logo = $row['logo_url'] ?? '';

        $new_logo = $current_logo;
        if (!empty($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $up = handle_image_upload($_FILES['logo'], $_SERVER['DOCUMENT_ROOT'].'/futsal/futsal-pj/uploads/teams');
            if (!$up['ok']) { exit($up['error']); }
            $new_logo = $up['path'];
        }

        $stmt = $mysqli->prepare("UPDATE teams SET tournament_id=?, name=?, abbreviation=?, city=?, logo_url=? WHERE id=?");
        $stmt->bind_param('issssi',$tournament_id,$name,$abbr,$city,$new_logo,$id);
        $stmt->execute();
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if ($id<=0) { exit('ID inválido'); }
        $stmt = $mysqli->prepare("DELETE FROM teams WHERE id=?");
        $stmt->bind_param('i',$id);
        $stmt->execute();
        break;

    default:
        http_response_code(400);
        exit('Ação desconhecida');
}

header('Location: /futsal-pj/admin/teams.php');
