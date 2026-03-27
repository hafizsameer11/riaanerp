<?php
session_start();

if (!isset($_SESSION['previous_url']) || $_SESSION['previous_url'] !== $_SERVER['REQUEST_URI']) {
    if (isset($_GET['from'])) {
        $_SESSION['previous_url'] = $_GET['from'];
    } elseif (isset($_SERVER['HTTP_REFERER'])) {
        $_SESSION['previous_url'] = $_SERVER['HTTP_REFERER'];
    } else {
        $_SESSION['previous_url'] = 'index.php';
    }
}

$previous = $_SESSION['previous_url'];

echo '<a href="' . htmlspecialchars($previous) . '" style="text-decoration: none; color: white; background-color: #1E2A38; padding:0; border-radius: 5px; font-family: Arial, sans-serif;margin-right:10px">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="30" height="30" color="#ffffff" fill="none">
    <path d="M15 6C15 6 9.00001 10.4189 9 12C8.99999 13.5812 15 18 15 18" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
</svg></a>';
