<?php
// csrf.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['_csrf'];
}
function csrf_validate(string $t): bool {
    return !empty($t) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $t);
}
