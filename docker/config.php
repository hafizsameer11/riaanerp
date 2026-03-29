<?php
/**
 * Used only inside Docker: compose bind-mounts this file over config.php and modules/config.php.
 * Production / XAMPP keep using the real project config files (unchanged).
 */

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "clientzone";

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

if (($h = getenv('DB_HOST')) !== false && $h !== '') {
    $db_host = $h;
}
$hostOverride = getenv('DB_HOST_OVERRIDE');
if ($hostOverride !== false && $hostOverride !== '') {
    $db_host = $hostOverride;
}

if (($u = getenv('DB_USER')) !== false && $u !== '') {
    $db_user = $u;
}
if (getenv('DB_PASS') !== false) {
    $db_pass = getenv('DB_PASS');
}
if (($n = getenv('DB_NAME')) !== false && $n !== '') {
    $db_name = $n;
}

$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error && ($db_host === "localhost" || $db_host === "127.0.0.1")) {
    $conn = @new mysqli("127.0.0.1", $db_user, $db_pass, $db_name);
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
