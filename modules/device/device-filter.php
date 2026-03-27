<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Temporary Debug Logger
file_put_contents('debug_filter.txt', print_r($_POST, true));

// Database connection
$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get POST data
$client_id = intval($_POST['client_id'] ?? 0);
$device_type = trim($_POST['device_type'] ?? '');
$location = trim($_POST['location'] ?? '');

$where = "d.is_deleted = 0";

if ($client_id) {
    $where .= " AND d.client_id = $client_id";
}
if (!empty($device_type)) {
    $device_type_safe = $conn->real_escape_string($device_type);
    $where .= " AND d.device_type = '$device_type_safe'";
}
if (!empty($location)) {
    $location_safe = $conn->real_escape_string($location);
    $where .= " AND d.location = '$location_safe'";
}

$query = "
    SELECT d.*, c.client_name
    FROM client_devices d
    LEFT JOIN clients c ON d.client_id = c.id
    WHERE $where
    ORDER BY d.created_at DESC
";

$result = $conn->query($query);

$output = '';

$i = 1;
if ($result && $result->num_rows > 0) {
    while ($device = $result->fetch_assoc()) {
        $output .= '<tr class="text-center">';
        $output .= '<td>' . $i++ . '</td>';
        $output .= '<td>' . htmlspecialchars($device['client_name']) . '</td>';
        $output .= '<td>' . htmlspecialchars($device['device_name']) . '</td>';
        $output .= '<td>' . htmlspecialchars($device['device_type']) . '</td>';
        $output .= '<td>' . htmlspecialchars($device['device_ip']) . '</td>';
        $output .= '<td>' . htmlspecialchars($device['location']) . '</td>';
        $output .= '<td>' . htmlspecialchars($device['username']) . '</td>';
        $output .= '<td>*****</td>';
        // $output .= '<td>' . htmlspecialchars($device['enable_username']) . '</td>';
        // $output .= '<td>*****</td>';
        $output .= '<td>' . htmlspecialchars($device['access_port']) . '</td>';
        $output .= '<td>
            <div class="btn-group">
                <a href="?view=' . $device['id'] . '" class="btn btn-sm" title="View">
                    <i class="fas fa-eye"></i>
                </a>
                <a href="javascript:void(0);" onclick="openEditModal(' . $device['id'] . ')" class="btn btn-sm" title="Edit">
                    <i class="fas fa-edit"></i>
                </a>
                <a href="javascript:void(0);" onclick="openDeleteModal(' . $device['id'] . ')" class="btn btn-sm text-danger" title="Delete">
                    <i class="fas fa-trash-alt"></i>
                </a>
            </div>
        </td>';
        $output .= '</tr>';
    }
} else {
    $output .= '<tr><td colspan="13" class="text-center">No devices found!</td></tr>';
}

echo $output;
?>
