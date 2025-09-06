<?php
// teams_action.php (create|update|delete)
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/upload_helper.php';

$action = $_POST['action'] ?? '';
$token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!csrf_validate($token)) {
    http_response_code(403);
    echo json_encode(['erro' => 'CSRF inválido']);
    exit;
}

$mysqli = get_mysqli();
$uploadBase = $_SERVER['DOCUMENT_ROOT'] . '/futsal-pj/uploads/teams';

try {
    switch ($action) {
        case 'create':
            $tournament_id = (int)($_POST['tournament_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $abbr = trim((string)($_POST['abbreviation'] ?? ''));
            $city = trim((string)($_POST['city'] ?? ''));
            if ($tournament_id <= 0 || $name === '') {
                http_response_code(400);
                echo json_encode(['erro' => 'Dados inválidos']);
                exit;
            }
            $up = handle_image_upload($_FILES['logo'] ?? [], $uploadBase);
            if (!$up['ok']) {
                http_response_code(400);
                echo json_encode(['erro' => $up['error']]);
                exit;
            }
            $logo = $up['path'] ?? '';
            $stmt = $mysqli->prepare("INSERT INTO teams (tournament_id,name,abbreviation,city,logo_url,created_at) VALUES (?,?,?,?,?,NOW())");
            $stmt->bind_param('issss', $tournament_id, $name, $abbr, $city, $logo);
            $stmt->execute();
            echo json_encode(['ok' => true, 'id' => $mysqli->insert_id]);
            exit;
        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $tournament_id = (int)($_POST['tournament_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $abbr = trim((string)($_POST['abbreviation'] ?? ''));
            $city = trim((string)($_POST['city'] ?? ''));
            if ($id <= 0 || $tournament_id <= 0 || $name === '') {
                http_response_code(400);
                echo json_encode(['erro' => 'Dados inválidos']);
                exit;
            }
            // obter logo atual
            $s = $mysqli->prepare("SELECT logo_url FROM teams WHERE id=?");
            $s->bind_param('i', $id);
            $s->execute();
            $row = $s->get_result()->fetch_assoc();
            $current = $row['logo_url'] ?? '';
            $newLogo = $current;
            if (!empty($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $up = handle_image_upload($_FILES['logo'], $uploadBase);
                if (!$up['ok']) {
                    http_response_code(400);
                    echo json_encode(['erro' => $up['error']]);
                    exit;
                }
                $newLogo = $up['path'];
                // opcional: apagar antigo ficheiro
                if ($current && file_exists($_SERVER['DOCUMENT_ROOT'] . $current)) {
                    @unlink($_SERVER['DOCUMENT_ROOT'] . $current);
                }
            }
            $stmt = $mysqli->prepare("UPDATE teams SET tournament_id=?, name=?, abbreviation=?, city=?, logo_url=? WHERE id=?");
            $stmt->bind_param('issssi', $tournament_id, $name, $abbr, $city, $newLogo, $id);
            $stmt->execute();
            echo json_encode(['ok' => true]);
            exit;
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['erro' => 'ID inválido']);
                exit;
            }
            // opcional: obter logo e apagar ficheiro
            $s = $mysqli->prepare("SELECT logo_url FROM teams WHERE id=?");
            $s->bind_param('i', $id);
            $s->execute();
            $r = $s->get_result()->fetch_assoc();
            if (!empty($r['logo_url']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $r['logo_url'])) {
                @unlink($_SERVER['DOCUMENT_ROOT'] . $r['logo_url']);
            }
            $stmt = $mysqli->prepare("DELETE FROM teams WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            echo json_encode(['ok' => true]);
            exit;
        default:
            http_response_code(400);
            echo json_encode(['erro' => 'Ação desconhecida']);
            exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro servidor', 'detalhe' => $e->getMessage()]);
    exit;
}
