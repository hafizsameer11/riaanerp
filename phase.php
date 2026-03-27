<?php
// Database credentials
$localhost = ($_SERVER['SERVER_NAME'] == 'localhost');

if ($localhost) {
    // Local development settings
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db = "clientzone";
} else {
    // Live server settings
    $host = "localhost";
    $user = "";
    $pass = "";
    $db = "clientzone";
}
$charset = 'utf8mb4';
// Create DSN and PDO
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    echo "✅ Connected to the database successfully.<br>";

    // 1. Execute ba8da2ce-8a55-411c-a6cb-0a3dc5f18639.sql
    $sql1 = file_get_contents('ba8da2ce-8a55-411c-a6cb-0a3dc5f18639.sql');
    $pdo->exec($sql1);
    echo "✅ Schema file 1 executed successfully.<br>";

    // 2. Execute privnotes.sql
    $sql2 = file_get_contents('privnotes.sql');
    $pdo->exec($sql2);
    echo "✅ Schema file 2 executed successfully.<br>";

    // 3. Safely add `currency_name` column if it doesn't exist
    $check = $pdo->query("SHOW COLUMNS FROM billing_category_price LIKE 'currency_name'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE billing_category_price ADD COLUMN currency_name VARCHAR(50) NULL");
        echo "✅ Column 'currency_name' added to billing_category_price.<br>";
    } else {
        echo "ℹ️ Column 'currency_name' already exists in billing_category_price.<br>";
    }

    echo "<br>🎉 All updates applied successfully.";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
