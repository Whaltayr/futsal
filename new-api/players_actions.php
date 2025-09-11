<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405); echo json_encode(['erro'=>'Método não permitido']); exit;
}

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/upload_helper.php';

$action = $_POST['action'] ?? '';
$token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!csrf_validate($token)) { http_response_code(403); echo json_encode(['erro'=>'CSRF inválido']); exit; }

$mysqli = get_mysqli();

$projectRoot = dirname(__DIR__);
$uploadBase = $projectRoot . '/uploads/players';

try {
  switch ($action) {
    case 'create': {
      $team_id = (int)($_POST['team_id'] ?? 0);
      $name = trim((string)($_POST['name'] ?? ''));
      $number = trim((string)($_POST['number'] ?? ''));
      $position = trim((string)($_POST['position'] ?? ''));
      $dob = $_POST['dob'] ?? null;
      $bi = trim((string)($_POST['bi'] ?? ''));
      if ($team_id<=0 || $name==='') { http_response_code(400); echo json_encode(['erro'=>'Dados incompletos']); exit; }

      $up = handle_image_upload($_FILES['photo'] ?? [], $uploadBase);
      if (!$up['ok']) { http_response_code(400); echo json_encode(['erro'=>$up['error']]); exit; }
      $photo = $up['path'] ?? null;

      $stmt = $mysqli->prepare("INSERT INTO players (team_id, name, number, position, dob, bi, photo_url, created_at) VALUES (?,?,?,?,?,?,?, NOW())");
      $stmt->bind_param('issssss', $team_id, $name, $number, $position, $dob, $bi, $photo);
      $stmt->execute();
      echo json_encode(['ok'=>true,'id'=>$mysqli->insert_id]); exit;
    }

    case 'update': {
      $id = (int)($_POST['id'] ?? 0);
      $team_id = (int)($_POST['team_id'] ?? 0);
      $name = trim((string)($_POST['name'] ?? ''));
      $number = trim((string)($_POST['number'] ?? ''));
      $position = trim((string)($_POST['position'] ?? ''));
      $dob = $_POST['dob'] ?? null;
      $bi = trim((string)($_POST['bi'] ?? ''));
      if ($id<=0 || $team_id<=0 || $name==='') { http_response_code(400); echo json_encode(['erro'=>'Dados inválidos']); exit; }

      $s = $mysqli->prepare("SELECT photo_url FROM players WHERE id=?");
      $s->bind_param('i', $id); $s->execute();
      $row = $s->get_result()->fetch_assoc();
      $current = $row['photo_url'] ?? null;
      $newPhoto = $current;

      if (!empty($_FILES['photo']) && (($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
        $up = handle_image_upload($_FILES['photo'], $uploadBase);
        if (!$up['ok']) { http_response_code(400); echo json_encode(['erro'=>$up['error']]); exit; }
        $newPhoto = $up['path'];
        if ($current && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'],'/') . $current)) {
          @unlink(rtrim($_SERVER['DOCUMENT_ROOT'],'/') . $current);
        }
      }

      $stmt = $mysqli->prepare("UPDATE players SET team_id=?, name=?, number=?, position=?, dob=?, bi=?, photo_url=? WHERE id=?");
      $stmt->bind_param('issssssi', $team_id, $name, $number, $position, $dob, $bi, $newPhoto, $id);
      $stmt->execute();
      echo json_encode(['ok'=>true]); exit;
    }

    case 'delete': {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) { http_response_code(400); echo json_encode(['erro'=>'ID inválido']); exit; }

      $s = $mysqli->prepare("SELECT photo_url FROM players WHERE id=?");
      $s->bind_param('i', $id); $s->execute();
      $r = $s->get_result()->fetch_assoc();
      if (!empty($r['photo_url']) && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'],'/') . $r['photo_url'])) {
        @unlink(rtrim($_SERVER['DOCUMENT_ROOT'],'/') . $r['photo_url']);
      }

      $stmt = $mysqli->prepare("DELETE FROM players WHERE id=?");
      $stmt->bind_param('i', $id); $stmt->execute();
      echo json_encode(['ok'=>true]); exit;
    }

    default:
      http_response_code(400); echo json_encode(['erro'=>'Ação desconhecida']); exit;
  }
} catch (Throwable $e) {
  http_response_code(500); echo json_encode(['erro'=>'Erro servidor','detalhe'=>$e->getMessage()]); exit;
}