<?php
// api/upload_helper.php
declare(strict_types=1);

function ensure_dir(string $dir): void {
    if (!is_dir($dir)) { mkdir($dir, 0775, true); }
}

function sanitize_filename(string $name): string {
    $name = preg_replace('/[^a-zA-Z0-9_\.-]/','_', $name);
    return substr($name, 0, 80);
}

/**
 * @return array{ok:bool,path?:string,error?:string}
 */
function handle_image_upload(array $file, string $destDir, int $maxBytes=2_000_000): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok'=>true, 'path'=>'']; // upload opcional
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok'=>false, 'error'=>'Falha no upload (erro '.$file['error'].')'];
    }
    if ($file['size'] > $maxBytes) {
        return ['ok'=>false, 'error'=>'Ficheiro excede o limite de 2MB'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $extMap = [
        'image/jpeg'=>'jpg',
        'image/png'=>'png',
        'image/webp'=>'webp',
        'image/gif'=>'gif'
    ];
    if (!isset($extMap[$mime])) {
        return ['ok'=>false, 'error'=>'Formato inválido (permitidos: jpg, png, webp, gif)'];
    }

    ensure_dir($destDir);

    $base = pathinfo($file['name'], PATHINFO_FILENAME);
    $base = sanitize_filename($base);
    $ext  = $extMap[$mime];
    $fn   = $base . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    $dest = rtrim($destDir,'/').'/'.$fn;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok'=>false, 'error'=>'Não foi possível gravar o ficheiro'];
    }
    // caminho público relativo
    $public = str_replace($_SERVER['DOCUMENT_ROOT'], '', realpath($dest)) ?: $dest;
    return ['ok'=>true, 'path'=>$public];
}
