<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/connection.php';

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$sd = $_POST['start_date'] ?? '';
$ed = $_POST['end_date'] ?? '';

if ($id<=0 || $name==='' || $sd==='' || $ed==='') {
    http_response_code(400);
    exit('Dados inválidos');
}

$stmt = $mysqli->prepare("UPDATE tournaments SET name=?, start_date=?, end_date=? WHERE id=?");
$stmt->bind_param('sssi', $name, $sd, $ed, $id);
$stmt->execute();

header('Location: /futsal-pj/admin/tournaments.php');
