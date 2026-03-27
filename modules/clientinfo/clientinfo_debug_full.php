<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>STEP 0: PHP Executed\n";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "STEP 1: POST DATA\n";
    print_r($_POST);
    echo "\nFILES\n";
    print_r($_FILES);
}

include_once '../../config.php'; // Ensure this path is correct

if (array_key_exists('upload_doc', $_POST) && isset($_FILES['doc_file'])) {
    echo "STEP 2: Upload Triggered\n";
    $clientId = intval($_POST['client_id']);
    $docName = $_POST['doc_name'];
    $fileName = time() . '_' . basename($_FILES['doc_file']['name']);
    $targetDir = __DIR__ . "/uploads/";
    $targetFile = $targetDir . $fileName;

    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
        echo "STEP 3: Upload folder created\n";
    }

    if (!is_writable($targetDir)) {
        die("❌ STEP 4: Target folder not writable: $targetDir");
    }

    if (!move_uploaded_file($_FILES['doc_file']['tmp_name'], $targetFile)) {
        die("❌ STEP 5: Failed to move uploaded file");
    }

    echo "STEP 6: File moved successfully\n";

    $query = "INSERT INTO client_documents (client_id, doc_name, filename) VALUES ($clientId, '" . $conn->real_escape_string($docName) . "', '" . $conn->real_escape_string($fileName) . "')";
    echo "STEP 7: SQL: $query\n";

    if (!$conn->query($query)) {
        die("❌ STEP 8: DB insert failed: " . $conn->error);
    }

    echo "✅ STEP 9: Document saved\n";
    exit;
}

if (array_key_exists('add_support', $_POST)) {
    echo "STEP 2: Support Triggered\n";

    $clientId = intval($_POST['client_id']);
    $label = $conn->real_escape_string($_POST['label']);
    $type = $conn->real_escape_string($_POST['type']);
    $ip = $conn->real_escape_string($_POST['ip_address']);
    $username = $conn->real_escape_string($_POST['username']);
    $password = $conn->real_escape_string($_POST['password']);
    $note = $conn->real_escape_string($_POST['note']);

    $query = "INSERT INTO client_support_items (client_id, label, type, ip_address, username, password, note) VALUES ($clientId, '$label', '$type', '$ip', '$username', '$password', '$note')";
    echo "STEP 3: SQL: $query\n";

    if (!$conn->query($query)) {
        die("❌ STEP 4: DB insert failed: " . $conn->error);
    }

    echo "✅ STEP 5: Support item saved\n";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Client Info Debug</title></head>
<body style="font-family:sans-serif; padding:20px; background:#f9f9f9;">
<h2>📎 Upload Document</h2>
<form method="post" enctype="multipart/form-data" action="clientinfo_debug_full.php">
  <input type="hidden" name="client_id" value="4">
  <input type="text" name="doc_name" placeholder="Document Name" required><br>
  <input type="file" name="doc_file" required><br>
  <button type="submit" name="upload_doc">Upload</button>
</form>

<h2>🛠 Add Support Item</h2>
<form method="post" action="clientinfo_debug_full.php">
  <input type="hidden" name="client_id" value="4">
  <input type="text" name="label" placeholder="Label" required><br>
  <input type="text" name="type" placeholder="Type" required><br>
  <input type="text" name="ip_address" placeholder="IP"><br>
  <input type="text" name="username" placeholder="Username"><br>
  <input type="text" name="password" placeholder="Password"><br>
  <input type="text" name="note" placeholder="Note"><br>
  <button type="submit" name="add_support">Save Support</button>
</form>
</body>
</html>

