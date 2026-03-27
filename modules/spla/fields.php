<?php
$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct


// Add new field
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['field_name'];
    $type = $_POST['field_type'];
    $required = isset($_POST['is_required']) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO spla_custom_fields (field_name, field_type, is_required) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $name, $type, $required);
    $stmt->execute();
    header("Location: fields.php");
    exit;
}

// Delete field
if (isset($_GET['delete'])) {
    $fieldId = intval($_GET['delete']);
    $conn->query("DELETE FROM spla_custom_fields WHERE id = $fieldId");
    $conn->query("DELETE FROM spla_custom_data WHERE field_id = $fieldId");
    header("Location: fields.php");
    exit;
}

$fields = $conn->query("SELECT * FROM spla_custom_fields ORDER BY field_name ASC");
?>

<h2>Manage Custom Fields for SPLA Licensing</h2>

<form method="post" style="margin-bottom:20px;">
    <label>Field Name:</label>
    <input type="text" name="field_name" required>
    <label>Type:</label>
    <select name="field_type">
        <option value="text">Text</option>
        <option value="number">Number</option>
        <option value="textarea">Textarea</option>
        <option value="dropdown">Dropdown (manual values)</option>
    </select>
    <label><input type="checkbox" name="is_required"> Required</label>
    <button type="submit">Add Field</button>
</form>

<table border="1" cellpadding="5" cellspacing="0">
    <tr>
        <th>Field Name</th>
        <th>Type</th>
        <th>Required</th>
        <th>Actions</th>
    </tr>
    <?php while ($row = $fields->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['field_name']) ?></td>
        <td><?= $row['field_type'] ?></td>
        <td><?= $row['is_required'] ? 'Yes' : 'No' ?></td>
        <td><a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this field and all its data?')">Delete</a></td>
    </tr>
    <?php endwhile; ?>
</table>

