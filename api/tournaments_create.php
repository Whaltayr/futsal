<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/connection.php';

$name = trim($_POST['name'] ?? '');
$sd   = $_POST['start_date'] ?? '';
$ed   = $_POST['end_date'] ?? '';

if ($name === '' || $sd === '' || $ed === '') {
    http_response_code(400);
    exit('Dados incompletos');
}

$stmt = $mysqli->prepare("INSERT INTO tournaments (name, start_date, end_date) VALUES (?,?,?)");
$stmt->bind_param('sss', $name, $sd, $ed);
$stmt->execute();

header('Location: /futsal-pj/admin/tournaments.php');
