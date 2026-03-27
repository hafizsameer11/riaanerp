<?php
// Temporary test page to debug form submission
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
require_once __DIR__ . '/../includes/functions.php';

echo "<pre>";
echo "POST data:\n";
print_r($_POST);
echo "\n\nGET data:\n";
print_r($_GET);
echo "\n\nSites in database:\n";
$sites = db()->query("SELECT id, name, failover_command FROM sites")->fetchAll(PDO::FETCH_ASSOC);
print_r($sites);
echo "</pre>";

