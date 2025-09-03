<?php
// create_player.php - produção
// Colocar em /var/www/html/futsal-pj/api/create_player.php
declare(strict_types=1);

ini_set('display_errors','0'); ini_set('log_errors','1');

header('Content-Type: application/json; charset=utf-8');

try {
    // includes essenciais (lançam JSON 401 se auth falhar)
    require_once __DIR__ . '/auth_check.php';
    require_once __DIR__ . '/connection.php';
    require_once __DIR__ . '/csrf.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['erro'=>'Dependência ausente','detalhe'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro'=>'Método não permitido. Use POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// CSRF: aceitar token via POST (form) ou header X-CSRF-Token (AJAX)
$csrf_token = (string)($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (!csrf_validate($csrf_token)) {
    http_response_code(403);
    echo json_encode(['erro'=>'Token CSRF inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// leitura e validação mínima dos campos
$name = trim((string)($_POST['name'] ?? ''));
$number = trim((string)($_POST['number'] ?? ''));
$position = trim((string)($_POST['position'] ?? ''));
$dob = trim((string)($_POST['dob'] ?? '')); // formato YYYY-MM-DD preferível
$bi = trim((string)($_POST['bi'] ?? ''));
$team_id = isset($_POST['team_id']) ? intval($_POST['team_id']) : 0;

if ($name === '') {
    http_response_code(400);
    echo json_encode(['erro'=>'Campo "name" é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// upload config
$UPLOAD_DIR = __DIR__ . '/../uploads/photos/';
$PUBLIC_PREFIX = '/futsal-pj/uploads/photos/'; // ajustar se o path público for outro
$MAX_BYTES = 2 * 1024 * 1024; // 2 MB
$ALLOWED_MIME = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp'];

$photo_url = null;
$thumb_url = null;

try {
    $mysqli = get_mysqli();

    // validar team_id se fornecido (opcional)
    if ($team_id > 0) {
        $s = $mysqli->prepare("SELECT id FROM teams WHERE id = ? LIMIT 1");
        $s->bind_param('i', $team_id); $s->execute();
        if (!$s->get_result()->fetch_assoc()) {
            http_response_code(400);
            echo json_encode(['erro'=>'Equipa especificada não existe.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // tratar upload, se houver
    if (!empty($_FILES['photo']['name'])) {
        if (!isset($_FILES['photo']['error']) || is_array($_FILES['photo']['error'])) {
            throw new RuntimeException('Erro no upload.');
        }
        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Erro no upload (code '.$_FILES['photo']['error'].').');
        }
        if ($_FILES['photo']['size'] > $MAX_BYTES) {
            throw new RuntimeException('Ficheiro demasiado grande. Máx 2MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['photo']['tmp_name']) ?: '';
        if (!array_key_exists($mime, $ALLOWED_MIME)) {
            throw new RuntimeException('Tipo de ficheiro não permitido. JPG/PNG/WEBP apenas.');
        }

        $ext = $ALLOWED_MIME[$mime];
        $basename = bin2hex(random_bytes(8)) . '_' . time();
        $filename = $basename . $ext;
        $target = $UPLOAD_DIR . $filename;

        if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0755, true) && !is_dir($UPLOAD_DIR)) {
            throw new RuntimeException('Falha ao criar pasta de upload.');
        }

        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
            throw new RuntimeException('Falha ao mover ficheiro enviado.');
        }
        chmod($target, 0644);

        // gerar thumbnail 200x200
        $thumb_name = $basename . '_thumb' . $ext;
        $thumb_path = $UPLOAD_DIR . $thumb_name;
        create_thumbnail($target, $thumb_path, 200, 200);

        $photo_url = $PUBLIC_PREFIX . $filename;
        $thumb_url = $PUBLIC_PREFIX . $thumb_name;
    }

    // inserir jogador
    $stmt = $mysqli->prepare("INSERT INTO players (team_id, name, number, position, birthdate, bi_hash, photo_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param('issssss', $team_id, $name, $number, $position, $dob, $bi, $photo_url);
    $stmt->execute();
    $player_id = (int)$mysqli->insert_id;

    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'mensagem' => 'Jogador criado.',
        'player_id' => $player_id,
        'photo' => $photo_url,
        'thumb' => $thumb_url
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    error_log('create_player error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro'=>'Erro no servidor','detalhe'=> $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

/* --- helper thumbnail --- */
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
    $nw = max(1, (int)($w * $scale)); $nh = max(1, (int)($h * $scale));
    $thumb = imagecreatetruecolor($nw, $nh);
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
        imagefilledrectangle($thumb, 0, 0, $nw, $nh, $transparent);
    }
    imagecopyresampled($thumb, $img, 0,0,0,0, $nw, $nh, $w, $h);
    switch ($mime) {
        case 'image/jpeg': imagejpeg($thumb, $dst, 85); break;
        case 'image/png':  imagepng($thumb, $dst); break;
        case 'image/webp': imagewebp($thumb, $dst, 85); break;
    }
    imagedestroy($thumb);
    imagedestroy($img);
}
