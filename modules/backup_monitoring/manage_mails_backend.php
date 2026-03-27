<?php
session_start();
include_once '../../config.php';
include_once '../components/permissioncheck.php';

// Check if user has access to backup monitoring
if (!hasPermission('billing', 'backup_monitoring')) {
    http_response_code(403);
    die('Access denied');
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Fetch all backup mails
if ($action === 'fetch') {
    $res = $conn->query("
        SELECT * FROM backup_mails 
        ORDER BY 
            CASE mail_type 
                WHEN 'CLIENT' THEN 1
                WHEN 'VEEAM' THEN 2
                WHEN 'VEEAMCLOUD' THEN 3
                WHEN 'NAS' THEN 4
            END
    ");
    
    $hasAccess = hasPermission('billing', 'backup_monitoring');
    $hasEdit = $hasAccess;
    $hasDelete = $hasAccess;
    
    $rowCount = 0;
    while ($r = $res->fetch_assoc()) {
        $rowCount++;
        $statusBadge = $r['is_active'] == 1 
            ? '<span class="badge bg-success">Active</span>' 
            : '<span class="badge bg-secondary">Inactive</span>';
        $lastUpdated = $r['updated_at'] ? date('Y-m-d H:i', strtotime($r['updated_at'])) : 'N/A';
        
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($r['mail_type']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($r['email_address']) . "</td>";
        echo "<td><small>" . htmlspecialchars($r['imap_host']) . "</small></td>";
        echo "<td>$statusBadge</td>";
        echo "<td>$lastUpdated</td>";
        echo "<td>";
        if ($hasEdit) {
            echo "<button class='btn btn-sm btn-primary me-1' onclick='openEdit({$r['id']})' title='Edit'><i class='fas fa-edit'></i></button>";
        }
        if ($hasDelete) {
            echo "<button class='btn btn-sm btn-danger' onclick='openDelete({$r['id']})' title='Delete'><i class='fas fa-trash'></i></button>";
        }
        echo "</td>";
        echo "</tr>";
    }
    
    if ($rowCount === 0) {
        echo "<tr><td colspan='6' class='text-center text-muted py-4'>";
        echo "<i class='fas fa-info-circle me-2'></i>No backup mail configurations found. Click 'Add Backup Mail' to create one.";
        echo "</td></tr>";
    }
    
    exit;
}

// Get backup mail
if ($action === 'get') {
    $id = intval($_POST['id']);
    $r = $conn->query("SELECT * FROM backup_mails WHERE id=$id")->fetch_assoc();
    if (!$r) {
        echo json_encode(['error' => 'Backup mail not found']);
        exit;
    }
    // Don't send password in response for security
    $r['app_password'] = '';
    echo json_encode($r);
    exit;
}

// Add backup mail
if ($action === 'add') {
    if (!hasPermission('billing', 'backup_monitoring')) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }
    
    $mailType = $_POST['mail_type'] ?? '';
    $emailAddress = $_POST['email_address'] ?? '';
    $appPassword = $_POST['app_password'] ?? '';
    $imapHost = $_POST['imap_host'] ?? '{imap.gmail.com:993/imap/ssl}INBOX';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Check if mail_type already exists
    $check = $conn->prepare("SELECT id FROM backup_mails WHERE mail_type = ?");
    $check->bind_param('s', $mailType);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['error' => 'Mail type already exists']);
        exit;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO backup_mails (mail_type, email_address, app_password, imap_host, is_active)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('ssssi', $mailType, $emailAddress, $appPassword, $imapHost, $isActive);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Error adding backup mail: ' . $stmt->error]);
    }
    exit;
}

// Edit backup mail
if ($action === 'edit') {
    if (!hasPermission('billing', 'backup_monitoring')) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }
    
    $id = intval($_POST['id']);
    $mailType = $_POST['mail_type'] ?? '';
    $emailAddress = $_POST['email_address'] ?? '';
    $appPassword = $_POST['app_password'] ?? '';
    $imapHost = $_POST['imap_host'] ?? '{imap.gmail.com:993/imap/ssl}INBOX';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // If password is empty, don't update it
    if (empty($appPassword)) {
        $stmt = $conn->prepare("
            UPDATE backup_mails 
            SET mail_type = ?, email_address = ?, imap_host = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->bind_param('sssii', $mailType, $emailAddress, $imapHost, $isActive, $id);
    } else {
        $stmt = $conn->prepare("
            UPDATE backup_mails 
            SET mail_type = ?, email_address = ?, app_password = ?, imap_host = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->bind_param('ssssii', $mailType, $emailAddress, $appPassword, $imapHost, $isActive, $id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Error updating backup mail: ' . $stmt->error]);
    }
    exit;
}

// Delete backup mail
if ($action === 'delete') {
    if (!hasPermission('billing', 'backup_monitoring')) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }
    
    $id = intval($_POST['id']);
    $conn->query("DELETE FROM backup_mails WHERE id=$id");
    echo json_encode(['success' => true]);
    exit;
}

// Get email credentials for cron scripts
if ($action === 'get_credentials') {
    $mailType = $_GET['type'] ?? $_POST['type'] ?? '';
    
    if (empty($mailType)) {
        echo json_encode(['error' => 'Mail type required']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT email_address, app_password, imap_host FROM backup_mails WHERE mail_type = ? AND is_active = 1");
    $stmt->bind_param('s', $mailType);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'email' => $row['email_address'],
            'password' => $row['app_password'],
            'imap_host' => $row['imap_host']
        ]);
    } else {
        echo json_encode(['error' => 'No active backup mail found for type: ' . $mailType]);
    }
    exit;
}
