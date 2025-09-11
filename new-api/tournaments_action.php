<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405); echo json_encode(['erro'=>'Método não permitido']); exit;
}

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/csrf.php';

$action = $_POST['action'] ?? '';
$token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!csrf_validate($token)) { http_response_code(403); echo json_encode(['erro'=>'CSRF inválido']); exit; }

$mysqli = get_mysqli();

try {
  switch ($action) {
    case 'create':
      $name = trim((string)($_POST['name'] ?? ''));
      $sd = $_POST['start_date'] ?? '';
      $ed = $_POST['end_date'] ?? '';
      if ($name === '' || $sd === '' || $ed === '') { http_response_code(400); echo json_encode(['erro'=>'Dados incompletos']); exit; }
      $stmt = $mysqli->prepare("INSERT INTO tournaments (name,start_date,end_date,created_at) VALUES (?,?,?,NOW())");
      $stmt->bind_param('sss',$name,$sd,$ed); $stmt->execute();
      echo json_encode(['ok'=>true,'id'=>$mysqli->insert_id]); exit;

    case 'update':
      $id = (int)($_POST['id'] ?? 0);
      $name = trim((string)($_POST['name'] ?? ''));
      $sd = $_POST['start_date'] ?? '';
      $ed = $_POST['end_date'] ?? '';
      if ($id<=0 || $name==='' || $sd==='' || $ed==='') { http_response_code(400); echo json_encode(['erro'=>'Dados inválidos']); exit; }
      $stmt = $mysqli->prepare("UPDATE tournaments SET name=?, start_date=?, end_date=? WHERE id=?");
      $stmt->bind_param('sssi',$name,$sd,$ed,$id); $stmt->execute();
      echo json_encode(['ok'=>true]); exit;

    case 'delete':
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) { http_response_code(400); echo json_encode(['erro'=>'ID inválido']); exit; }
      $stmt = $mysqli->prepare("DELETE FROM tournaments WHERE id=?");
      $stmt->bind_param('i',$id); $stmt->execute();
      echo json_encode(['ok'=>true]); exit;

    default:
      http_response_code(400); echo json_encode(['erro'=>'Ação desconhecida']); exit;
  }
} catch (Throwable $e) {
  http_response_code(500); echo json_encode(['erro'=>'Erro servidor','detalhe'=>$e->getMessage()]); exit;
}