<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/connection.php';

$id = (int)($_POST['id'] ?? 0);
if ($id<=0) { http_response_code(400); exit('ID inválido'); }

$stmt = $mysqli->prepare("DELETE FROM tournaments WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();

header('Location: /futsal-pj/admin/tournaments.php');
