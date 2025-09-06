<?php
// api/auth_check.php
declare(strict_types=1);
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(302);
    header('Location: /futsal-pj/login_form.php');
    exit;
}
