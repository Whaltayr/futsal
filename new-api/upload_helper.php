<?php
// upload_helper.php (simples)
declare(strict_types=1);

function ensure_dir(string $dir): void {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

function handle_image_upload(array $file, string $destDir, int $maxBytes = 2_000_000): array {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok'=>true,'path'=>''];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) return ['ok'=>false,'error'=>'Erro no upload'];
    if ($file['size'] > $maxBytes) return ['ok'=>false,'error'=>'Ficheiro demasiado grande (max 2MB)'];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    $map = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($map[$mime])) return ['ok'=>false,'error'=>'Formato inválido'];

    ensure_dir($destDir);
    $base = preg_replace('/[^a-z0-9_\-]/i','_', pathinfo($file['name'], PATHINFO_FILENAME));
    $fn = $base . '-' . bin2hex(random_bytes(6)) . '.' . $map[$mime];
    $dest = rtrim($destDir,'/').'/'.$fn;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return ['ok'=>false,'error'=>'Falha mover ficheiro'];
    chmod($dest, 0644);

    // retornar caminho público relativo assumindo DocumentRoot= /var/www/html
    $public = str_replace($_SERVER['DOCUMENT_ROOT'], '', realpath($dest));
    return ['ok'=>true,'path'=>$public];
}
