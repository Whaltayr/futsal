<?php
declare(strict_types=1);

function ensure_dir(string $dir): bool {
    return is_dir($dir) || mkdir($dir, 0775, true);
}

function handle_image_upload(array $file, string $destDir, int $maxBytes = 2_000_000): array {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok'=>true,'path'=>''];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok'=>false,'error'=>'Erro no upload (PHP code '.$file['error'].')'];
    }
    if ($file['size'] > $maxBytes) {
        return ['ok'=>false,'error'=>'Ficheiro demasiado grande (max 2MB)'];
    }
    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        return ['ok'=>false,'error'=>'Upload inválido (tmp ausente)'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    $map = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($map[$mime])) {
        return ['ok'=>false,'error'=>'Formato inválido (apenas jpeg, png, webp)'];
    }

    if (!ensure_dir($destDir)) {
        return ['ok'=>false,'error'=>'Não foi possível criar diretório de upload'];
    }
    if (!is_writable($destDir)) {
        return ['ok'=>false,'error'=>'Diretório de upload sem permissão: '.$destDir];
    }

    $base = preg_replace('/[^a-z0-9_\-]/i','_', pathinfo($file['name'], PATHINFO_FILENAME));
    $fn = $base . '-' . bin2hex(random_bytes(6)) . '.' . $map[$mime];
    $dest = rtrim($destDir,'/').'/'.$fn;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        // Log útil para diagnosticar em server logs
        error_log('move_uploaded_file falhou: tmp='.$file['tmp_name'].' dest='.$dest);
        return ['ok'=>false,'error'=>'Falha mover ficheiro'];
    }
    @chmod($dest, 0644);

    // Monta URL pública relativa ao DOCUMENT_ROOT (funciona em / ou /futsal-pj)
    $public = str_replace(rtrim($_SERVER['DOCUMENT_ROOT'],'/'), '', realpath($dest));
    if ($public === $dest) {
        // fallback: ao menos retorna um caminho relativo a /uploads
        $public = '/uploads/teams/'.$fn;
    }
    return ['ok'=>true,'path'=>$public];
}