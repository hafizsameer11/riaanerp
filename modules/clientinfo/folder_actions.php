<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../../config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : (isset($_GET['client_id']) ? intval($_GET['client_id']) : 0);

if ($client_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Client ID']);
    exit;
}

// Create folder
if ($action === 'create_folder') {
    $folder_name = trim($_POST['folder_name'] ?? '');
    $parent_folder_id = !empty($_POST['parent_folder_id']) ? intval($_POST['parent_folder_id']) : NULL;
    
    // Build redirect URL with current folder context
    $redirect_url = "client_documents.php?client_id=$client_id";
    if ($parent_folder_id !== NULL) {
        $redirect_url .= "&folder_id=$parent_folder_id";
    }
    
    if (empty($folder_name)) {
        $_SESSION['message'] = [
            'type' => 'danger',
            'text' => 'Folder name cannot be empty.'
        ];
        header("Location: $redirect_url");
        exit;
    }
    
    // Check if folder name already exists for this client in the same parent
    $checkStmt = $conn->prepare("SELECT id FROM client_folders WHERE client_id = ? AND folder_name = ? AND (parent_folder_id = ? OR (parent_folder_id IS NULL AND ? IS NULL))");
    $checkStmt->bind_param("isii", $client_id, $folder_name, $parent_folder_id, $parent_folder_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['message'] = [
            'type' => 'danger',
            'text' => 'A folder with this name already exists in this location.'
        ];
        header("Location: $redirect_url");
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO client_folders (client_id, folder_name, parent_folder_id) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $client_id, $folder_name, $parent_folder_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = [
            'type' => 'success',
            'text' => 'Folder created successfully.'
        ];
    } else {
        $_SESSION['message'] = [
            'type' => 'danger',
            'text' => 'Failed to create folder: ' . $stmt->error
        ];
    }
    $stmt->close();
    header("Location: $redirect_url");
    exit;
}

// Delete folder
if ($action === 'delete_folder') {
    $folder_id = intval($_GET['folder_id'] ?? $_POST['folder_id'] ?? 0);
    
    // Get parent folder ID to redirect back to parent after deletion
    $parent_folder_id = NULL;
    if ($folder_id > 0) {
        $parent_query = $conn->query("SELECT parent_folder_id FROM client_folders WHERE id = $folder_id AND client_id = $client_id");
        if ($parent_row = $parent_query->fetch_assoc()) {
            $parent_folder_id = $parent_row['parent_folder_id'];
        }
    }
    
    // Build redirect URL - go back to parent folder after deletion
    $redirect_url = "client_documents.php?client_id=$client_id";
    if ($parent_folder_id !== NULL) {
        $redirect_url .= "&folder_id=$parent_folder_id";
    }
    
    if ($folder_id === 0) {
        $_SESSION['message'] = [
            'type' => 'danger',
            'text' => 'Invalid folder ID.'
        ];
        header("Location: $redirect_url");
        exit;
    }
    
    // Check if folder belongs to this client
    $checkStmt = $conn->prepare("SELECT id FROM client_folders WHERE id = ? AND client_id = ?");
    $checkStmt->bind_param("ii", $folder_id, $client_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['message'] = [
            'type' => 'danger',
            'text' => 'Folder not found or access denied.'
        ];
        header("Location: $redirect_url");
        exit;
    }
    
    // Check if folder has subfolders or documents
    $subfolders = $conn->query("SELECT COUNT(*) as count FROM client_folders WHERE parent_folder_id = $folder_id")->fetch_assoc();
    $documents = $conn->query("SELECT COUNT(*) as count FROM client_documents WHERE folder_id = $folder_id")->fetch_assoc();
    
    if ($subfolders['count'] > 0 || $documents['count'] > 0) {
        $_SESSION['message'] = [
            'type' => 'danger',
            'text' => 'Cannot delete folder. Please delete all subfolders and documents first.'
        ];
        header("Location: $redirect_url");
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM client_folders WHERE id = ? AND client_id = ?");
    $stmt->bind_param("ii", $folder_id, $client_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = [
            'type' => 'success',
            'text' => 'Folder deleted successfully.'
        ];
    } else {
        $_SESSION['message'] = [
            'type' => 'danger',
            'text' => 'Failed to delete folder: ' . $stmt->error
        ];
    }
    $stmt->close();
    header("Location: $redirect_url");
    exit;
}

// Get folders for dropdown (AJAX)
if ($action === 'get_folders') {
    $parent_id = isset($_GET['parent_id']) && $_GET['parent_id'] !== '' ? intval($_GET['parent_id']) : NULL;
    
    $sql = "SELECT id, folder_name FROM client_folders WHERE client_id = ?";
    $params = [$client_id];
    $types = "i";
    
    if ($parent_id === NULL) {
        $sql .= " AND parent_folder_id IS NULL";
    } else {
        $sql .= " AND parent_folder_id = ?";
        $params[] = $parent_id;
        $types .= "i";
    }
    
    $sql .= " ORDER BY folder_name ASC";
    
    $stmt = $conn->prepare($sql);
    if (count($params) > 1) {
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param($types, $client_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $folders = [];
    while ($row = $result->fetch_assoc()) {
        $folders[] = $row;
    }
    
    echo json_encode(['success' => true, 'folders' => $folders]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;

