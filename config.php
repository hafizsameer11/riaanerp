<?php


$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "clientzone";

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

$hostOverride = getenv('DB_HOST_OVERRIDE');
if ($hostOverride !== false && $hostOverride !== '') {
    $db_host = $hostOverride;
}

$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error && $db_host === "localhost") {
    // Local Mac fallback when MySQL socket path is unavailable.
    $conn = @new mysqli("127.0.0.1", $db_user, $db_pass, $db_name);
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
