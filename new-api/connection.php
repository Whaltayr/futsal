<?php
// connection.php (dev)
declare(strict_types=1);
$DB_HOST='localhost';
$DB_USER='root';
$DB_PASS='';
$DB_NAME='futsal';

function get_mysqli(): mysqli {
    static $m = null;
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    if ($m !== null) return $m;
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $m = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $m->set_charset('utf8mb4');
    return $m;
}
