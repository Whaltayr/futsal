<?php
// upload_helper.php
declare(strict_types=1);

function ensure_dir(string $dir): bool {
    return is_dir($dir) || mkdir($dir, 0775, true);
}

function handle_image_upload(array $file, string $destDir, int $maxBytes = 2_000_000): array {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok'=>true,'path'=>'']; // sem novo ficheiro
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok'=>false,'error'=>'Erro no upload (PHP code '.$file['error'].')'];
    }
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) {
        return ['ok'=>false,'error'=>'Ficheiro demasiado grande (máx 2MB)'];
    }
    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        return ['ok'=>false,'error'=>'Upload inválido (tmp ausente)'];
    }

    // Descobrir extensão a partir do MIME (mais seguro)
    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($file['tmp_name']);
    }
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extMap[$mime])) {
        return ['ok'=>false,'error'=>'Formato inválido (apenas jpeg, png, webp)'];
    }

    if (!ensure_dir($destDir)) {
        return ['ok'=>false,'error'=>'Não foi possível criar diretório de upload'];
    }
    if (!is_writable($destDir)) {
        return ['ok'=>false,'error'=>'Diretório sem permissão: '.$destDir];
    }

    $base = preg_replace('/[^a-z0-9_\-]/i','_', pathinfo($file['name'] ?? 'logo', PATHINFO_FILENAME));
    if ($base === '' || $base === '_') $base = 'logo';
    $fn   = $base . '-' . bin2hex(random_bytes(6)) . '.' . $extMap[$mime];
    $dest = rtrim($destDir,'/').'/'.$fn;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        error_log('move_uploaded_file falhou: tmp='.$file['tmp_name'].' dest='.$dest);
        return ['ok'=>false,'error'=>'Falha ao mover o ficheiro'];
    }
    @chmod($dest, 0644);

    // Construir URL público relativo ao DocumentRoot
    $realDest = realpath($dest) ?: $dest;
    $docRoot  = rtrim(str_replace('\\','/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $path     = str_replace('\\','/', $realDest);
    $public   = '';

    if ($docRoot !== '' && strpos($path, $docRoot) === 0) {
        $public = substr($path, strlen($docRoot));
        if ($public === '' || $public[0] !== '/') $public = '/'.$public;
    }
    if ($public === '') {
        // fallback: tenta padrão /uploads/...
        $public = '/uploads/'.basename(dirname($dest)).'/'.$fn;
    }

    return ['ok'=>true,'path'=>$public];
}