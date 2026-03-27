<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Declare all expected variables upfront
$viewing = false;
$editing = false;
$view_data = null;
$support = null;
$docs = null;
$clients = null;
$id = null;

// Include database configuration
include_once '../../config.php'; // Ensure this path is correct
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle new client creation
if (isset($_POST['save_client'])) {
    $stmt = $conn->prepare("INSERT INTO clients (client_name, email, contact_person, office_number, accounts_contact, accounts_email, address, notes, vat_number, registration_number, billing_type, status, sales_person, billing_country, currency, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    if (!$stmt) {
        die("❌ SQL Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("sssssssssssssss",
        $_POST['client_name'],
        $_POST['email'],
        $_POST['contact_person'],
        $_POST['office_number'],
        $_POST['accounts_contact'],
        $_POST['accounts_email'],
        $_POST['address'],
        $_POST['notes'],
        $_POST['vat_number'],
        $_POST['registration_number'],
        $_POST['billing_type'],
        $_POST['status'],
        $_POST['sales_person'],
        $_POST['billing_country'],
        $_POST['currency']
    );

    $stmt->execute();

    if ($stmt->affected_rows < 1) {
        die("❌ Insert failed. MySQL error: " . $stmt->error);
    }

    header("Location: clientinfo.php#submitted");
    exit;
}

// Handle update client
if (isset($_POST['update_client'])) {
    $stmt = $conn->prepare("UPDATE clients SET client_name=?, email=?, contact_person=?, office_number=?, accounts_contact=?, accounts_email=?, address=?, notes=?, vat_number=?, registration_number=?, billing_type=?, status=?, sales_person=?, billing_country=?, currency=? WHERE id=?");

    if (!$stmt) {
        die("❌ SQL Prepare failed (update): " . $conn->error);
    }

    $stmt->bind_param("sssssssssssssssi",
        $_POST['client_name'],
        $_POST['email'],
        $_POST['contact_person'],
        $_POST['office_number'],
        $_POST['accounts_contact'],
        $_POST['accounts_email'],
        $_POST['address'],
        $_POST['notes'],
        $_POST['vat_number'],
        $_POST['registration_number'],
        $_POST['billing_type'],
        $_POST['status'],
        $_POST['sales_person'],
        $_POST['billing_country'],
        $_POST['currency'],
        $_POST['client_id']
    );

    $stmt->execute();

    if ($stmt->affected_rows < 0) {
        die("❌ Update failed. MySQL error: " . $stmt->error);
    }

    header("Location: clientinfo.php?view=" . $_POST['client_id']);
    exit;
}

// Handle document upload
if (isset($_POST['upload_doc']) && isset($_FILES['doc_file'])) {
    $clientId = intval($_POST['client_id']);
    $docName = $_POST['doc_name'];
    $fileName = time() . '_' . basename($_FILES['doc_file']['name']);
    $targetDir = __DIR__ . "/uploads/";
    $targetFile = $targetDir . $fileName;

    if (!move_uploaded_file($_FILES['doc_file']['tmp_name'], $targetFile)) {
        die("❌ Failed to move uploaded file. Check folder permissions.");
    }

    $sql = "INSERT INTO client_documents (client_id, doc_name, filename) VALUES ($clientId, '" . $conn->real_escape_string($docName) . "', '" . $conn->real_escape_string($fileName) . "')";
    if (!$conn->query($sql)) {
        die("❌ Failed to insert document: " . $conn->error);
    }

    header("Location: clientinfo.php?view=$clientId");
    exit;
}

// Handle support item add/update/delete with note field
if (isset($_POST['add_support'])) {
    $sql = "INSERT INTO client_support_items (client_id, label, type, ip_address, username, password, note) VALUES (
        " . intval($_POST['client_id']) . ",
        '" . $conn->real_escape_string($_POST['label']) . "',
        '" . $conn->real_escape_string($_POST['type']) . "',
        '" . $conn->real_escape_string($_POST['ip_address']) . "',
        '" . $conn->real_escape_string($_POST['username']) . "',
        '" . $conn->real_escape_string($_POST['password']) . "',
        '" . $conn->real_escape_string($_POST['note']) . "'
    )";
    if (!$conn->query($sql)) {
        die("❌ Support insert failed: " . $conn->error);
    }

    header("Location: clientinfo.php?view=" . intval($_POST['client_id']));
    exit;
}

if (isset($_POST['update_support'])) {
    $conn->query("UPDATE client_support_items SET
        label='" . $conn->real_escape_string($_POST['label']) . "',
        type='" . $conn->real_escape_string($_POST['type']) . "',
        ip_address='" . $conn->real_escape_string($_POST['ip_address']) . "',
        username='" . $conn->real_escape_string($_POST['username']) . "',
        password='" . $conn->real_escape_string($_POST['password']) . "',
        note='" . $conn->real_escape_string($_POST['note']) . "'
        WHERE id=" . intval($_POST['support_id'])
    );
    header("Location: clientinfo.php?view=" . intval($_POST['client_id']));
    exit;
}

if (isset($_GET['delete_support'])) {
    $sid = intval($_GET['delete_support']);
    $cid = intval($_GET['view']);
    $conn->query("DELETE FROM client_support_items WHERE id = $sid");
    header("Location: clientinfo.php?view=$cid");
    exit;
}

if (isset($_GET['delete_doc'])) {
    $did = intval($_GET['delete_doc']);
    $cid = intval($_GET['view']);
    $conn->query("DELETE FROM client_documents WHERE id = $did");
    header("Location: clientinfo.php?view=$cid");
    exit;
}

if (isset($_GET['delete_client'])) {
    $id = intval($_GET['delete_client']);
    $conn->query("DELETE FROM clients WHERE id = $id");
    header("Location: clientinfo.php");
    exit;
}

if (isset($_GET['view'])) {
    $viewing = true;
    $id = intval($_GET['view']);
} elseif (isset($_GET['edit'])) {
    $editing = true;
    $id = intval($_GET['edit']);
    $viewing = true;
}

if (isset($id)) {
    $result = $conn->query("SELECT * FROM clients WHERE id = $id");
    if ($result && $result->num_rows > 0) {
        $view_data = $result->fetch_assoc();
        $support = $conn->query("SELECT * FROM client_support_items WHERE client_id = $id");
        $docs = $conn->query("SELECT * FROM client_documents WHERE client_id = $id");
    }
}

$clients = $conn->query("SELECT * FROM clients ORDER BY created_at DESC");
if (!$clients) {
    die("❌ Query failed for clients: " . $conn->error);
}
?>