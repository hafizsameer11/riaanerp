<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);


include_once '../../config.php'; // Ensure this path is correct
$id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
if ($id === 0) {
    die("Invalid Client ID");
}

$client = $conn->query("SELECT client_name FROM clients WHERE id = $id")->fetch_assoc();
$client_name = $client ? htmlspecialchars($client['client_name']) : 'Unknown Client';

// Get current folder view (for navigation)
$current_folder_id = isset($_GET['folder_id']) && $_GET['folder_id'] !== '' ? intval($_GET['folder_id']) : NULL;

// Handle document upload
if (isset($_POST['upload_doc']) && isset($_FILES['doc_file'])) {
    if ($_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['message'] = [
            'type' => 'danger',
            'text' => "Error uploading file: " . $_FILES['doc_file']['error']
        ];
    } else {
        $docName = $_POST['doc_name']; // Will be used in prepared statement, no need to escape
        $fileName = time() . '_' . basename($_FILES['doc_file']['name']);
        $targetDir = __DIR__ . "/uploads/";
        $targetFile = $targetDir . $fileName;
        $folder_id = !empty($_POST['folder_id']) ? intval($_POST['folder_id']) : NULL;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        if (file_exists($_FILES['doc_file']['tmp_name'])) {
            if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $targetFile)) {
                // Use prepared statement to prevent SQL injection and syntax errors
                // Check if folder_id column exists, if not use old query
                $stmt = $conn->prepare("INSERT INTO client_documents (client_id, name, filename, folder_id) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("issi", $id, $docName, $fileName, $folder_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Fallback for old schema
                    $stmt = $conn->prepare("INSERT INTO client_documents (client_id, name, filename) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $id, $docName, $fileName);
                    $stmt->execute();
                    $stmt->close();
                }
                $_SESSION['message'] = [
                    'type' => 'success',
                    'text' => "File uploaded successfully."
                ];
            } else {
                $_SESSION['message'] = [
                    'type' => 'danger',
                    'text' => "Failed to upload file. Please check file permissions or disk space."
                ];
            }
        } else {
            $_SESSION['message'] = [
                'type' => 'danger',
                'text' => "Temporary file not found. Possible upload error."
            ];
        }
    }
    $redirect_url = "client_documents.php?client_id=$id";
    if ($current_folder_id !== NULL) {
        $redirect_url .= "&folder_id=$current_folder_id";
    }
    header("Location: $redirect_url");
    exit;
}

// Handle document delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $docId = intval($_GET['delete']);
    $file = $conn->query("SELECT filename FROM client_documents WHERE id = $docId AND client_id = $id")->fetch_assoc();
    if ($file) {
        @unlink(__DIR__ . "/uploads/" . $file['filename']);
    }
    $conn->query("DELETE FROM client_documents WHERE id = $docId AND client_id = $id");
    $_SESSION['message'] = [
        'type' => 'success',
        'text' => "Document deleted successfully."
    ];
    $redirect_url = "client_documents.php?client_id=$id";
    if ($current_folder_id !== NULL) {
        $redirect_url .= "&folder_id=$current_folder_id";
    }
    header("Location: $redirect_url");
    exit;
}

// search document
$client_id = $_GET['client_id'] ?? null;
$search = $_GET['search'] ?? '';

if (!$client_id) {
    echo "<p>No client selected.</p>";
    exit;
}

// Get folders for current view
$folders_sql = "SELECT * FROM client_folders WHERE client_id = ?";
$folders_params = [$client_id];
$folders_types = "i";

if ($current_folder_id === NULL) {
    $folders_sql .= " AND parent_folder_id IS NULL";
} else {
    $folders_sql .= " AND parent_folder_id = ?";
    $folders_params[] = $current_folder_id;
    $folders_types .= "i";
}

$folders_sql .= " ORDER BY folder_name ASC";
$folders_stmt = $conn->prepare($folders_sql);
if (count($folders_params) > 1) {
    $folders_stmt->bind_param($folders_types, ...$folders_params);
} else {
    $folders_stmt->bind_param($folders_types, $client_id);
}
$folders_stmt->execute();
$folders_result = $folders_stmt->get_result();
// Store folders in array for reuse
$folders = [];
while ($folder = $folders_result->fetch_assoc()) {
    $folders[] = $folder;
}

// Get current folder path for breadcrumb
$folder_path = [];
$current_folder_info = NULL;
if ($current_folder_id !== NULL) {
    $folder_id = $current_folder_id;
    while ($folder_id !== NULL) {
        $folder_info = $conn->query("SELECT id, folder_name, parent_folder_id FROM client_folders WHERE id = $folder_id")->fetch_assoc();
        if ($folder_info) {
            array_unshift($folder_path, $folder_info);
            $folder_id = $folder_info['parent_folder_id'];
            if ($current_folder_info === NULL) {
                $current_folder_info = $folder_info;
            }
        } else {
            break;
        }
    }
}

// --- Fetch documents based on search and folder ---
$sql = "SELECT * FROM client_documents WHERE client_id = ?";
$params = [$client_id];
$types = "i";

if ($current_folder_id === NULL) {
    $sql .= " AND (folder_id IS NULL OR folder_id = 0)";
} else {
    $sql .= " AND folder_id = ?";
    $params[] = $current_folder_id;
    $types .= "i";
}

if (!empty($search)) {
    $sql .= " AND name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$docs = $stmt->get_result();
?>

<!DOCTYPE html>
<html>

<head>
    <title>📁 Client Documents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            overflow-x: hidden;
        }
        .container {
            max-width: 100%;
            padding: 15px;
        }
        .folder-item {
            cursor: pointer;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            transition: background-color 0.2s;
        }
        .folder-item:hover {
            background-color: #e9ecef;
        }
        .breadcrumb-nav {
            margin-bottom: 15px;
        }
        .breadcrumb-nav a {
            text-decoration: none;
            color: #0d6efd;
        }
        .folder-icon {
            color: #ffc107;
            margin-right: 8px;
        }
        .upload-form-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .btn-compact {
            padding: 6px 12px;
            font-size: 14px;
        }
        .form-control-sm-custom {
            padding: 6px 12px;
            font-size: 14px;
        }
        @media (max-width: 768px) {
            .upload-form-section .row > div {
                margin-bottom: 10px;
            }
        }
    </style>
    <script>
        const clientId = <?= json_encode($id) ?>;
        const currentFolderId = <?= json_encode($current_folder_id) ?>;
        
        function confirmDelete(docId) {
            if (confirm("Are you sure you want to delete this document?")) {
                let url = "?client_id=" + clientId + "&delete=" + docId;
                if (currentFolderId !== null) {
                    url += "&folder_id=" + currentFolderId;
                }
                window.location.href = url;
            }
        }
        
        function confirmDeleteFolder(folderId, folderName) {
            if (confirm("Are you sure you want to delete the folder '" + folderName + "'? This action cannot be undone if the folder contains files or subfolders.")) {
                window.location.href = "folder_actions.php?action=delete_folder&folder_id=" + folderId + "&client_id=" + clientId;
            }
        }
        
        function openFolder(folderId) {
            window.location.href = "?client_id=" + clientId + "&folder_id=" + folderId;
        }
        
        function showCreateFolderModal() {
            document.getElementById('createFolderModal').style.display = 'block';
        }
        
        function closeCreateFolderModal() {
            document.getElementById('createFolderModal').style.display = 'none';
            document.getElementById('folderNameInput').value = '';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('createFolderModal');
            if (event.target == modal) {
                closeCreateFolderModal();
            }
        }
    </script>
</head>

<body class="p-3">
    <div class="container-fluid">
        <!-- Bootstrap Alert -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= $_SESSION['message']['type'] ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['message']['text']) ?>
                <!-- <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button> -->
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <div class="d-flex align-items-center">
            <?php

            // Store the current URL in the session as the previous URL
            if (!isset($_SESSION['previous_url']) || $_SESSION['previous_url'] !== $_SERVER['REQUEST_URI']) {
                $_SESSION['previous_url'] = $_SERVER['HTTP_REFERER'] ?? 'index.php';
            }

            // Retrieve the previous URL from the session
            $previous = $_SESSION['previous_url'];

            echo '<a href="' . htmlspecialchars($previous) . '" style="text-decoration: none; color: white; background-color: #1E2A38; padding:0; border-radius: 5px; font-family: Arial, sans-serif;margin-right:10px">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="30" height="30" color="#ffffff" fill="none">
    <path d="M15 6C15 6 9.00001 10.4189 9 12C8.99999 13.5812 15 18 15 18" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
</svg></a>';
            ?>
            <h3><i class="fas fa-folder-open"></i> Documents for <span class="text-primary"><?= $client_name ?></span></h3>
        </div>

        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb-nav mt-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="?client_id=<?= $id ?>"><i class="fas fa-home"></i> Root</a>
                    </li>
                    <?php foreach ($folder_path as $folder): ?>
                        <li class="breadcrumb-item">
                            <a href="?client_id=<?= $id ?>&folder_id=<?= $folder['id'] ?>">
                                <?= htmlspecialchars($folder['folder_name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex gap-2 mb-3">
            <button type="button" class="btn btn-primary btn-compact" onclick="showCreateFolderModal()">
                <i class="fas fa-folder-plus"></i> Create Folder
            </button>
        </div>

        <!-- Create Folder Modal -->
        <div id="createFolderModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow: auto;">
            <div style="background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 400px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h5 class="mb-3"><i class="fas fa-folder-plus"></i> Create New Folder</h5>
                <form method="POST" action="folder_actions.php">
                    <input type="hidden" name="action" value="create_folder">
                    <input type="hidden" name="client_id" value="<?= $id ?>">
                    <?php if ($current_folder_id !== NULL): ?>
                        <input type="hidden" name="parent_folder_id" value="<?= $current_folder_id ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="folderNameInput" class="form-label small">Folder Name</label>
                        <input type="text" class="form-control form-control-sm-custom" id="folderNameInput" name="folder_name" required placeholder="Enter folder name">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-compact"><i class="fas fa-check"></i> Create</button>
                        <button type="button" class="btn btn-secondary btn-compact" onclick="closeCreateFolderModal()"><i class="fas fa-times"></i> Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Upload Form Section -->
        <div class="upload-form-section">
            <h6 class="mb-3"><i class="fas fa-upload"></i> Upload Document</h6>
            <form method="POST" enctype="multipart/form-data">
                <div class="row g-2">
                    <div class="col-md-3">
                        <input type="text" name="doc_name" class="form-control form-control-sm-custom" placeholder="Document Name" required>
                    </div>
                    <div class="col-md-3">
                        <input type="file" name="doc_file" class="form-control form-control-sm-custom" required>
                    </div>
                    <div class="col-md-3">
                        <select name="folder_id" class="form-select form-control-sm-custom">
                            <option value="">Current location</option>
                            <?php if ($current_folder_id !== NULL): ?>
                                <option value="<?= $current_folder_id ?>" selected>Current Folder</option>
                            <?php endif; ?>
                            <?php 
                            // Get all folders for this client for dropdown
                            $all_folders_stmt = $conn->prepare("SELECT id, folder_name, parent_folder_id FROM client_folders WHERE client_id = ? ORDER BY folder_name ASC");
                            $all_folders_stmt->bind_param("i", $client_id);
                            $all_folders_stmt->execute();
                            $all_folders_result = $all_folders_stmt->get_result();
                            while ($folder_option = $all_folders_result->fetch_assoc()): 
                                if ($folder_option['id'] != $current_folder_id): // Don't show current folder twice
                            ?>
                                <option value="<?= $folder_option['id'] ?>"><?= htmlspecialchars($folder_option['folder_name']) ?></option>
                            <?php 
                                endif;
                            endwhile; 
                            $all_folders_stmt->close();
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" name="upload_doc" class="btn btn-success btn-compact w-100">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <form method="GET" action="" class="my-3">
            <input type="hidden" name="client_id" value="<?= htmlspecialchars($client_id) ?>">
            <?php if ($current_folder_id !== NULL): ?>
                <input type="hidden" name="folder_id" value="<?= $current_folder_id ?>">
            <?php endif; ?>

            <div class="row g-2 align-items-center">
                <!-- Search Input -->
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input
                            type="text"
                            name="search"
                            class="form-control form-control-sm-custom"
                            placeholder="Search document name..."
                            value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>

                <!-- Buttons -->
                <div class="col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-compact">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="?client_id=<?= htmlspecialchars($client_id) ?><?= $current_folder_id !== NULL ? '&folder_id=' . $current_folder_id : '' ?>" class="btn btn-secondary btn-compact">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>


        <!-- Folders Section -->
        <?php if (count($folders) > 0): ?>
            <div class="mt-3">
                <h5 class="mb-3"><i class="fas fa-folder"></i> Folders</h5>
                <div class="row g-2">
                    <?php foreach ($folders as $folder): ?>
                        <div class="col-md-3 col-sm-6">
                            <div class="folder-item" onclick="openFolder(<?= $folder['id'] ?>)">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="flex-grow-1">
                                        <i class="fas fa-folder folder-icon"></i>
                                        <strong class="small"><?= htmlspecialchars($folder['folder_name']) ?></strong>
                                    </div>
                                    <button class="btn btn-sm text-danger p-1" onclick="event.stopPropagation(); confirmDeleteFolder(<?= $folder['id'] ?>, '<?= htmlspecialchars(addslashes($folder['folder_name'])) ?>')" title="Delete Folder">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Documents Section -->
        <div class="mt-3">
            <h5 class="mb-3"><i class="fas fa-file"></i> Documents</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <th style="width:30%;">Document Name</th>
                            <th>File Name</th>
                            <th style="width:80px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($docs->num_rows > 0): ?>
                            <?php while ($doc = $docs->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($doc['name']) ?></td>
                                    <td>
                                        <a href="uploads/<?= htmlspecialchars($doc['filename']) ?>" target="_blank" class="text-decoration-none">
                                            <i class="fas fa-file"></i> <?= htmlspecialchars($doc['filename']) ?>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm text-danger p-1" onclick="confirmDelete(<?= $doc['id'] ?>)" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">
                                    <?php if (count($folders) > 0): ?>
                                        No documents in this folder.
                                    <?php else: ?>
                                        No documents found. Create a folder or upload a document to get started.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>