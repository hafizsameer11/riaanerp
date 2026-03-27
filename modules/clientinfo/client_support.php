<?php

  // Live server settings
include_once '../../config.php'; // Ensure this path is correct
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$client_res = $conn->query("SELECT client_name FROM clients WHERE id = $client_id");
$client_data = $client_res->fetch_assoc();
$client_name = $client_data ? $client_data['client_name'] : "Unknown";
if ($client_id === 0)
  die("Invalid Client ID");

// Export
if (isset($_GET['export'])) {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment;filename="support_export_client_' . $client_id . '.csv"');
  $out = fopen("php://output", "w");
  fputcsv($out, ['Description', 'Serial', 'Make', 'Model', 'Location', 'Username', 'Password', 'IP Address', 'Note']);
  $res = $conn->query("SELECT * FROM support_data WHERE client_id = $client_id");
  while ($row = $res->fetch_assoc()) {
    fputcsv($out, [
      $row['description'],
      $row['serial'],
      $row['make'],
      $row['model'],
      $row['location'],
      $row['username'],
      $row['password'],
      $row['ipaddress'],
      $row['note']
    ]);
  }
  fclose($out);
  exit;
}

// Handle delete
if (isset($_GET['delete'])) {
  $delete_id = intval($_GET['delete']);
  $conn->query("DELETE FROM support_data WHERE id = $delete_id AND client_id = $client_id");
  header("Location: ?client_id=$client_id");
  exit;
}

// Handle add or update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $desc = $_POST['description'];
  $serial = $_POST['serial'];
  $make = $_POST['make'];
  $model = $_POST['model'];
  $location = $_POST['location'];
  $username = $_POST['username'];
  $password = $_POST['password'];
  $ipaddress = $_POST['ipaddress'];
  $note = $_POST['note'];
  $update_id = isset($_POST['update_id']) ? intval($_POST['update_id']) : 0;

  if ($update_id > 0) {
    $stmt = $conn->prepare("UPDATE support_data SET description=?, serial=?, make=?, model=?, location=?, username=?, password=?, ipaddress=?, note=? WHERE id=? AND client_id=?");
    $stmt->bind_param("ssssssssssi", $desc, $serial, $make, $model, $location, $username, $password, $ipaddress, $note, $update_id, $client_id);
    $stmt->execute();
  } else {
    $stmt = $conn->prepare("INSERT INTO support_data (client_id, description, serial, make, model, location, username, password, ipaddress, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssssss", $client_id, $desc, $serial, $make, $model, $location, $username, $password, $ipaddress, $note);
    $stmt->execute();
  }
  header("Location: ?client_id=$client_id");
  exit;
}


$edit_record = null;
if (isset($_GET['edit'])) {
  $edit_id = intval($_GET['edit']);
  $res = $conn->query("SELECT * FROM support_data WHERE id = $edit_id AND client_id = $client_id");
  $edit_record = $res->fetch_assoc();
}
$supports = $conn->query("SELECT * FROM support_data WHERE client_id = $client_id ORDER BY id DESC");

?>

<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Client Support</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
  <div class="container mt-4">
    <div class="d-flex align-items-center">
      <?php include('../components/Backbtn.php')  ?>
      <h3>Support Info for Client <span class="text-dark">#<?= $client_id ?> -<?= htmlspecialchars($client_name) ?></span></h3>
    </div>


    <form method="POST" class="row g-2 mb-4">
      <input type="hidden" name="update_id" value="<?= $edit_record['id'] ?? '' ?>">
      <div class="col-md"><input class="form-control" name="description" placeholder="Description"
          value="<?= $edit_record['description'] ?? '' ?>" required></div>
      <div class="col-md"><input class="form-control" name="serial" placeholder="Serial"
          value="<?= $edit_record['serial'] ?? '' ?>"></div>
      <div class="col-md"><input class="form-control" name="make" placeholder="Make"
          value="<?= $edit_record['make'] ?? '' ?>"></div>
      <div class="col-md"><input class="form-control" name="model" placeholder="Model"
          value="<?= $edit_record['model'] ?? '' ?>"></div>
      <div class="col-md"><input class="form-control" name="location" placeholder="Location"
          value="<?= $edit_record['location'] ?? '' ?>"></div>
      <div class="col-md"><input class="form-control" name="username" placeholder="Username"
          value="<?= $edit_record['username'] ?? '' ?>"></div>
      <div class="col-md"><input class="form-control" name="password" placeholder="Password"
          value="<?= $edit_record['password'] ?? '' ?>"></div>
      <div class="col-md"><input class="form-control" name="ipaddress" placeholder="IP Address"
          value="<?= $edit_record['ipaddress'] ?? '' ?>"></div>
      <div class="col-md"><input class="form-control" name="note" placeholder="Note"
          value="<?= $edit_record['note'] ?? '' ?>"></div>
      <div class="col-auto"><button class="btn btn-primary"> Save</button></div>
    </form>

    <table class="table table-bordered">
      <thead class="table-light">
        <tr>
          <th>Description</th>
          <th>Serial</th>
          <th>Make</th>
          <th>Model</th>
          <th>Location</th>
          <th>Username</th>
          <th>Password</th>
          <th>IP Address</th>
          <th>Note</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($r = $supports->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($r['description']) ?></td>
            <td><?= htmlspecialchars($r['serial']) ?></td>
            <td><?= htmlspecialchars($r['make']) ?></td>
            <td><?= htmlspecialchars($r['model']) ?></td>
            <td><?= htmlspecialchars($r['location']) ?></td>
            <td><?= htmlspecialchars($r['username']) ?></td>
            <td><?= htmlspecialchars($r['password']) ?></td>
            <td><?= htmlspecialchars($r['ipaddress']) ?></td>
            <td><?= htmlspecialchars($r['note']) ?></td>
            <td>
              <a href="?client_id=<?= $client_id ?>&edit=<?= $r['id'] ?>" class="btn btn-sm btn-primary">✏️</a>
              <a href="?client_id=<?= $client_id ?>&delete=<?= $r['id'] ?>" class="btn btn-sm btn-danger"
                onclick="return confirm('Delete this entry?')">🗑️</a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

    <a href="?client_id=<?= $client_id ?>&export=1" class="btn btn-success mt-2">📤 Export CSV</a>
  </div>
</body>

</html>