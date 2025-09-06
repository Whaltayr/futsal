<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/api/connection.php';

$id = (int)($_POST['id'] ?? 0);
if ($id<=0) { http_response_code(400); exit('ID inválido'); }

// opcional: obter logo para apagar ficheiro depois
// $stmt = $mysqli->prepare("SELECT logo_url FROM teams WHERE id=?");
// $stmt->bind_param('i',$id);
// $stmt->execute();
// $row = $stmt->get_result()->fetch_assoc();
// if (!empty($row['logo_url'])) { @unlink($_SERVER['DOCUMENT_ROOT'].$row['logo_url']); }

$stmt = $mysqli->prepare("DELETE FROM teams WHERE id=?");
$stmt->bind_param('i',$id);
$stmt->execute();

header('Location: /futsal-pj/admin/teams.php');
