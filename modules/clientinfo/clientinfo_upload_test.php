<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>STEP 0: File loaded successfully\n";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "STEP 1: POST data received\n";
    print_r($_POST);
    print_r($_FILES);
}

include_once '../../config.php'; // Ensure this path is correct
if (array_key_exists('upload_doc', $_POST) && isset($_FILES['doc_file'])) {
    echo "STEP 2: Upload_doc triggered\n";

    $clientId = intval($_POST['client_id']);
    $docName = $_POST['doc_name'];
    $fileName = time() . '_' . basename($_FILES['doc_file']['name']);
    $targetDir = __DIR__ . "/uploads/";
    $targetFile = $targetDir . $fileName;

    echo "STEP 3: Target file = $targetFile\n";

    if (!file_exists($targetDir)) {
        die("❌ STEP 4: Upload directory does not exist: $targetDir");
    }
    if (!is_writable($targetDir)) {
        die("❌ STEP 5: Upload directory not writable: $targetDir");
    }

    if (!move_uploaded_file($_FILES['doc_file']['tmp_name'], $targetFile)) {
        die("❌ STEP 6: move_uploaded_file failed\nTMP: " . $_FILES['doc_file']['tmp_name']);
    }

    echo "✅ STEP 7: File moved successfully\n";

    $query = "INSERT INTO client_documents (client_id, doc_name, filename) VALUES ($clientId, '" . $conn->real_escape_string($docName) . "', '" . $conn->real_escape_string($fileName) . "')";
    echo "STEP 8: Running SQL: $query\n";

    if (!$conn->query($query)) {
        die("❌ STEP 9: DB insert failed: " . $conn->error);
    }

    echo "✅ STEP 10: Document inserted into DB\n";
    exit;
}

if (array_key_exists('add_support', $_POST)) {
    echo "STEP 2: add_support triggered\n";

    $clientId = intval($_POST['client_id']);
    $label = $conn->real_escape_string($_POST['label']);
    $type = $conn->real_escape_string($_POST['type']);
    $ip = $conn->real_escape_string($_POST['ip_address']);
    $user = $conn->real_escape_string($_POST['username']);
    $pass = $conn->real_escape_string($_POST['password']);
    $note = $conn->real_escape_string($_POST['note']);

    $query = "INSERT INTO client_support_items (client_id, label, type, ip_address, username, password, note) VALUES ($clientId, '$label', '$type', '$ip', '$user', '$pass', '$note')";
    echo "STEP 3: Running SQL: $query\n";

    if (!$conn->query($query)) {
        die("❌ STEP 4: DB insert failed: " . $conn->error);
    }

    echo "✅ STEP 5: Support item inserted into DB\n";
    exit;
}
echo "No upload or support add triggered.";
?>

