<?php
// create_team.php
declare(strict_types=1);
ini_set('display_errors','1'); error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth_check.php';     // protege endpoint
require_once __DIR__ . '/connection.php';     // get_mysqli()

// configuração de upload
$UPLOAD_DIR = __DIR__ . '/../uploads/logos/';
$MAX_BYTES = 2 * 1024 * 1024; // 2 MB
$ALLOWED_MIME = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro'=>'Método não permitido. Use POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// campos do formulário
$name = trim((string)($_POST['name'] ?? ''));
$abbreviation = trim((string)($_POST['abbreviation'] ?? ''));
$city = trim((string)($_POST['city'] ?? ''));
$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;

if ($name === '') {
    http_response_code(400);
    echo json_encode(['erro'=>'O campo name é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $mysqli = get_mysqli();

    // validar/definir tournament_id (igual lógica ao exemplo anterior)
    if ($tournament_id > 0) {
        $s = $mysqli->prepare("SELECT id FROM tournaments WHERE id = ? LIMIT 1");
        $s->bind_param('i', $tournament_id); $s->execute();
        if (!$s->get_result()->fetch_assoc()) {
            http_response_code(400);
            echo json_encode(['erro'=>'Torneio especificado não existe.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        $r = $mysqli->query("SELECT id FROM tournaments ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
        if ($r && isset($r['id'])) $tournament_id = intval($r['id']);
        else {
            http_response_code(400);
            echo json_encode(['erro'=>'Não existe torneio. Crie um torneio primeiro.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // tratar upload se houver
    $logo_url = null;
    if (!empty($_FILES['logo']['name'])) {
        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erro no upload do ficheiro (error code: ' . $_FILES['logo']['error'] . ')');
        }
        if ($_FILES['logo']['size'] > $MAX_BYTES) {
            throw new Exception('Ficheiro demasiado grande. Máx 2MB.');
        }

        // MIME check
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['logo']['tmp_name']) ?: '';
        if (!array_key_exists($mime, $ALLOWED_MIME)) {
            throw new Exception('Tipo de ficheiro não permitido. Apenas JPG/PNG/WEBP.');
        }

        // montar nome único
        $ext = $ALLOWED_MIME[$mime];
        $basename = bin2hex(random_bytes(8)) . '_' . time();
        $filename = $basename . $ext;
        $target = $UPLOAD_DIR . $filename;

        // mover ficheiro
        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
            throw new Exception('Falha ao mover ficheiro enviado.');
        }
        // permissões seguras
        chmod($target, 0644);

        // gerar thumbnail (ex.: 200x200)
        $thumb_name = $basename . '_thumb' . $ext;
        $thumb_path = $UPLOAD_DIR . $thumb_name;
        create_thumbnail($target, $thumb_path, 200, 200);

        // url relativa a usar no front-end
        $logo_url = '/futsal-pj/uploads/logos/' . $filename;
        $thumb_url = '/futsal-pj/uploads/logos/' . $thumb_name;
    }

    // inserir equipa
    $ins = $mysqli->prepare("INSERT INTO teams (tournament_id, name, abbreviation, city, logo_url, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $ins->bind_param('issss', $tournament_id, $name, $abbreviation, $city, $logo_url);
    $ins->execute();
    $team_id = (int)$mysqli->insert_id;

    echo json_encode(['ok'=>true,'mensagem'=>'Equipa criada.','team_id'=>$team_id,'logo'=>$logo_url ?? null, 'thumb' => $thumb_url ?? null], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['erro'=>'Erro no servidor','detalhe'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Simple thumbnail generator using GD (built-in)
 */
function create_thumbnail(string $src, string $dst, int $maxW, int $maxH): void {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($src);
    switch ($mime) {
        case 'image/jpeg': $img = imagecreatefromjpeg($src); break;
        case 'image/png':  $img = imagecreatefrompng($src);  break;
        case 'image/webp': $img = imagecreatefromwebp($src); break;
        default: return;
    }
    if (!$img) return;
    $w = imagesx($img); $h = imagesy($img);
    $scale = min($maxW / $w, $maxH / $h, 1);
    $nw = (int)($w * $scale); $nh = (int)($h * $scale);
    $thumb = imagecreatetruecolor($nw, $nh);
    // preserve PNG transparency
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
        imagefilledrectangle($thumb, 0, 0, $nw, $nh, $transparent);
    }
    imagecopyresampled($thumb, $img, 0,0,0,0, $nw, $nh, $w, $h);
    // save
    switch ($mime) {
        case 'image/jpeg': imagejpeg($thumb, $dst, 85); break;
        case 'image/png':  imagepng($thumb, $dst); break;
        case 'image/webp': imagewebp($thumb, $dst, 85); break;
    }
    imagedestroy($thumb);
    imagedestroy($img);
}
