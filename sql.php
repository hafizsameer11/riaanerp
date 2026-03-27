<?php
$servername = "localhost";
$username = "";
$password = "";
$dbname = "clientzone";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

// ALTER TABLE SQL
$sql = <<<SQL
ALTER TABLE `registers`
  MODIFY `client_name` varchar(255) DEFAULT NULL,
  MODIFY `client_id` varchar(255) DEFAULT NULL,
  MODIFY `device_type` varchar(255) DEFAULT NULL,
  MODIFY `device_ip` varchar(255) DEFAULT NULL,
  MODIFY `device_ip_location` varchar(255) DEFAULT NULL,
  MODIFY `url` varchar(255) DEFAULT NULL,
  ADD COLUMN `name` varchar(255) NOT NULL,
  ADD COLUMN `surname` varchar(255) NOT NULL,
  ADD COLUMN `email` varchar(255) NOT NULL,
  ADD COLUMN `address` varchar(255) NOT NULL;
SQL;

// Run the query
if ($conn->query($sql) === TRUE) {
    echo "✅ `registers` table structure updated successfully.";
} else {
    echo "❌ Error updating table: " . $conn->error;
}

$conn->close();
?>
