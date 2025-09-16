<?php
// new-api/teams_actions.php — AJAX/JSON
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/upload_helper.php';

function jfail(int $code, string $msg, string $detail=''): void {
    http_response_code($code);
    echo json_encode(['ok'=>false,'erro'=>$msg,'detalhe'=>$detail], JSON_UNESCAPED_UNICODE);
    exit;
}
function jok(array $extra=[]): void {
    echo json_encode(['ok'=>true] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

$token  = $_POST['csrf'] ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (!csrf_validate($token)) jfail(403,'CSRF inválido');

$action = $_POST['action'] ?? '';
if (!$action) jfail(400,'Ação ausente');

$mysqli = get_mysqli();
$mysqli->set_charset('utf8mb4');

// Ajuste conforme a tua estrutura (se o app está em /futsal-pj)
$uploadBase = rtrim($_SERVER['DOCUMENT_ROOT'],'/') . '/futsal-pj/uploads/teams';

try {
    if ($action === 'create') {
        $tournament_id = (int)($_POST['tournament_id'] ?? 0);
        $name          = trim((string)($_POST['name'] ?? ''));
        $abbr          = trim((string)($_POST['abbreviation'] ?? ''));
        $group_label   = trim((string)($_POST['group_label'] ?? ''));
        $city          = trim((string)($_POST['city'] ?? ''));

        if ($tournament_id <= 0 || $name === '' || $group_label === '') {
            jfail(400,'Dados inválidos (torneio, nome e grupo são obrigatórios)');
        }

        $logo = '';
        if (!empty($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $up = handle_image_upload($_FILES['logo'], $uploadBase, 2_000_000);
            if (!$up['ok']) jfail(400, $up['error'] ?? 'Falha no upload do logo');
            $logo = $up['path'] ?? '';
        }

        $stmt = $mysqli->prepare("INSERT INTO teams (tournament_id, name, abbreviation, group_label, city, logo_url, created_at)
                                  VALUES (?,?,?,?,?,?,NOW())");
        if (!$stmt) jfail(500,'Falha preparar INSERT',$mysqli->error);
        if (!$stmt->bind_param('isssss', $tournament_id, $name, $abbr, $group_label, $city, $logo)) jfail(500,'Falha bind INSERT',$stmt->error);
        if (!$stmt->execute()) jfail(500,'Falha execute INSERT',$stmt->error);

        jok(['id'=>$stmt->insert_id]);
    }

    if ($action === 'update') {
        $id            = (int)($_POST['id'] ?? 0);
        $tournament_id = (int)($_POST['tournament_id'] ?? 0);
        $name          = trim((string)($_POST['name'] ?? ''));
        $abbr          = trim((string)($_POST['abbreviation'] ?? ''));
        $group_label   = trim((string)($_POST['group_label'] ?? ''));
        $city          = trim((string)($_POST['city'] ?? ''));

        if ($id <= 0 || $tournament_id <= 0 || $name === '' || $group_label === '') {
            jfail(400,'Dados inválidos');
        }

        // Logo atual
        $s = $mysqli->prepare("SELECT logo_url FROM teams WHERE id=?");
        if (!$s) jfail(500,'Falha preparar SELECT',$mysqli->error);
        $s->bind_param('i',$id);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $current = $row['logo_url'] ?? '';
        $newLogo = $current;

        // Novo ficheiro?
        if (!empty($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $up = handle_image_upload($_FILES['logo'], $uploadBase, 2_000_000);
            if (!$up['ok']) jfail(400, $up['error'] ?? 'Falha no upload do logo');
            $newLogo = $up['path'] ?? $current;

            if ($current && $newLogo !== $current) {
                $oldDisk = $_SERVER['DOCUMENT_ROOT'] . $current;
                $doc = realpath($_SERVER['DOCUMENT_ROOT']) ?: '';
                $oldReal = realpath($oldDisk) ?: '';
                if ($doc && $oldReal && strpos($oldReal, $doc) === 0) @unlink($oldReal);
            }
        }

        $stmt = $mysqli->prepare("UPDATE teams
                                  SET tournament_id=?, name=?, abbreviation=?, group_label=?, city=?, logo_url=?
                                  WHERE id=?");
        if (!$stmt) jfail(500,'Falha preparar UPDATE',$mysqli->error);
        if (!$stmt->bind_param('isssssi', $tournament_id, $name, $abbr, $group_label, $city, $newLogo, $id)) jfail(500,'Falha bind UPDATE',$stmt->error);
        if (!$stmt->execute()) jfail(500,'Falha execute UPDATE',$stmt->error);

        jok(['id'=>$id]);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) jfail(400,'ID inválido');

        $s = $mysqli->prepare("SELECT logo_url FROM teams WHERE id=?");
        if (!$s) jfail(500,'Falha preparar SELECT',$mysqli->error);
        $s->bind_param('i',$id);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        if (!empty($r['logo_url'])) {
            $disk = $_SERVER['DOCUMENT_ROOT'] . $r['logo_url'];
            $doc  = realpath($_SERVER['DOCUMENT_ROOT']) ?: '';
            $real = realpath($disk) ?: '';
            if ($doc && $real && strpos($real, $doc) === 0) @unlink($real);
        }

        $stmt = $mysqli->prepare("DELETE FROM teams WHERE id=?");
        if (!$stmt) jfail(500,'Falha preparar DELETE',$mysqli->error);
        if (!$stmt->bind_param('i',$id)) jfail(500,'Falha bind DELETE',$stmt->error);
        if (!$stmt->execute()) jfail(500,'Falha execute DELETE',$stmt->error);

        jok();
    }

    jfail(400,'Ação desconhecida');

} catch (Throwable $e) {
    jfail(500,'Erro servidor',$e->getMessage());
}