<?php
$id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
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




// Live server settings
include_once '../../config.php'; // Ensure this path is correct

// Handle new client creation
if (isset($_POST['save_client'])) {
  // Ensure all optional fields are set
  $_POST['currency_symbol'] = $_POST['currency_symbol'] ?? null;

  $stmt = $conn->prepare("INSERT INTO clients (client_name, number, email, contact_person, office_number, accounts_contact, accounts_email, address, notes, vat_number, registration_number, billing_type, status, sales_person, billing_country, currency, currency_symbol, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
  echo '<pre>';
  print_r($_POST);
  echo '</pre>';
  // exit;
  if (!$stmt) {
    die("❌ SQL Prepare failed: " . $conn->error);
  }

  $stmt->bind_param(
    "sssssssssssssssss",
    $_POST['client_name'],
    $_POST['number'],
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
    $_POST['currency_symbol']
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
  $stmt = $conn->prepare("UPDATE clients SET client_name=?, number=?, email=?, contact_person=?, office_number=?, accounts_contact=?, accounts_email=?, address=?, notes=?, vat_number=?, registration_number=?, billing_type=?, status=?, sales_person=?, billing_country=?, currency=?, currency_symbol=? WHERE id=?");

  if (!$stmt) {
    die("❌ SQL Prepare failed (update): " . $conn->error);
  }

  $client_id = (int) $_POST['client_id'];

  $stmt->bind_param(
    "sssssssssssssssssi",
    $_POST['client_name'],
    $_POST['number'],              // ✅ now included
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
    $_POST['currency_symbol'],
    $client_id
  );

  if (!$stmt->execute()) {
    die("❌ Execution failed: " . $stmt->error);
  }

  header("Location: clientinfo.php?view=" . $client_id);
  exit;
}



// Handle document upload
if (isset($_POST['upload_doc']) && isset($_FILES['doc_file'])) {
  $clientId = intval($_POST['client_id']);
  $docName = $_POST['name'];
  $fileName = time() . '_' . basename($_FILES['doc_file']['name']);
  $targetFile = $targetDir . $fileName;

  if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $targetFile)) {
    $conn->query("INSERT INTO client_documents (client_id, name, filename) VALUES ($clientId, '" . $conn->real_escape_string($docName) . "', '" . $conn->real_escape_string($fileName) . "')");
  }
  header("Location: clientinfo.php?view=$clientId");
  exit;
}

// Handle support item add/update/delete with note field
if (isset($_POST['add_support'])) {
  $extra = json_encode(['note' => $_POST['note']]);
  $conn->query("INSERT INTO client_support_items (client_id, label, type, ip_address, username, password, extra) VALUES (
        " . intval($_POST['client_id']) . ",
        '" . $conn->real_escape_string($_POST['label']) . "',
        '" . $conn->real_escape_string($_POST['type']) . "',
        '" . $conn->real_escape_string($_POST['ip_address']) . "',
        '" . $conn->real_escape_string($_POST['username']) . "',
        '" . $conn->real_escape_string($_POST['password']) . "',
        '" . $conn->real_escape_string($extra) . "'
    )");
  header("Location: clientinfo.php?view=" . intval($_POST['client_id']));
  exit;
}

if (isset($_POST['update_support'])) {
  $conn->query(
    "UPDATE client_support_items SET
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
session_start();
include('../components/permissioncheck.php')

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/client.css">

  <meta charset="UTF-8">
  <title>Client Management</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 20px;
      background: #f9f9f9;
    }

    h2 {
      color: #333;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }

    th,
    td {
      padding: 10px;
      border: 1px solid #ccc;
      text-align: left;
    }

    th {
      background-color: #eee;
    }

    form.client-form {
      display: none;
      margin-bottom: 20px;
      background: #fff;
      padding: 15px;
      border: 1px solid #ddd;
    }

    input,
    select,
    textarea {
      width: 100%;
      padding: 5px;
      margin-bottom: 10px;
    }

    button {
      padding: 8px 12px;
    }

    .view-box {
      margin-top: 30px;
      padding: 15px;
      background: #fff;
      border: 1px solid #ccc;
    }
  </style>

  <script>
    function addToDropdown(id, promptText) {
      let value = prompt(promptText);
      if (value) {
        let dropdown = document.getElementById(id);
        let option = document.createElement("option");
        option.text = value;
        option.value = value;
        dropdown.add(option);
        dropdown.value = value;
      }
    }

    function removeFromDropdown(id) {
      let dropdown = document.getElementById(id);
      if (dropdown.selectedIndex > -1) {
        dropdown.remove(dropdown.selectedIndex);
      }
    }
  </script>

</head>

<body>


  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const form = document.querySelector('.client-form');
      if (window.location.hash === '#add') {
        form.style.display = 'block';
      }
      if (window.location.hash === '#submitted') {
        form.style.display = 'none';
      }
    });
  </script>

  <?php if ($editing): ?>
    <form method="post" class="container bg-light p-4 rounded shadow-sm my-4">
      <h3 class="mb-4 text-primary">Edit Client</h3>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Client Name</label>
          <input type="text" name="client_name" class="form-control"
            value="<?= htmlspecialchars($view_data['client_name']) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Mobile Number</label>
          <input type="number" name="number" class="form-control" value="<?= htmlspecialchars($view_data['number']) ?>"
            required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="text" name="email" class="form-control" value="<?= htmlspecialchars($view_data['email']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Contact Person</label>
          <input type="text" name="contact_person" class="form-control"
            value="<?= htmlspecialchars($view_data['contact_person']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Office Number</label>
          <input type="text" name="office_number" class="form-control"
            value="<?= htmlspecialchars($view_data['office_number']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Accounts Contact</label>
          <input type="text" name="accounts_contact" class="form-control"
            value="<?= htmlspecialchars($view_data['accounts_contact']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Accounts Email</label>
          <input type="text" name="accounts_email" class="form-control"
            value="<?= htmlspecialchars($view_data['accounts_email']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Address</label>
          <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($view_data['address']) ?>">
        </div>
        <div class="col-md-12">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($view_data['notes']) ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">VAT Number</label>
          <input type="text" name="vat_number" class="form-control"
            value="<?= htmlspecialchars($view_data['vat_number']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Registration Number</label>
          <input type="text" name="registration_number" class="form-control"
            value="<?= htmlspecialchars($view_data['registration_number']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Billing Type</label>
          <select name="billing_type" class="form-select">
            <option value="Invoice" <?= $view_data['billing_type'] == 'Invoice' ? 'selected' : '' ?>>Invoice</option>
            <option value="Debit Order" <?= $view_data['billing_type'] == 'Debit Order' ? 'selected' : '' ?>>Debit Order
            </option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="Active" <?= $view_data['status'] == 'Active' ? 'selected' : '' ?>>Active</option>
            <option value="Suspended" <?= $view_data['status'] == 'Suspended' ? 'selected' : '' ?>>Suspended</option>
            <option value="Cancelled" <?= $view_data['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
            <option value="Lead" <?= $view_data['status'] == 'Lead' ? 'selected' : '' ?>>Lead</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Sales Person</label>
          <input type="text" name="sales_person" class="form-control"
            value="<?= htmlspecialchars($view_data['sales_person']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Billing Country</label>
          <select name="billing_country" class="form-select">
            <option value="RSA" <?= $view_data['billing_country'] == 'RSA' ? 'selected' : '' ?>>RSA</option>
            <option value="Namibia" <?= $view_data['billing_country'] == 'Namibia' ? 'selected' : '' ?>>Namibia</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Currency</label>
          <input type="text" name="currency" class="form-control" value="<?= htmlspecialchars($view_data['currency']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Currency Symbol</label>
          <input type="text" name="currency_symbol" class="form-control"
            value="<?= empty($view_data['currency_symbol']) || $view_data['currency_symbol'] == null ? '' : htmlspecialchars($view_data['currency_symbol']) ?>">
        </div>
      </div>

      <input type="hidden" name="client_id" value="<?= $id ?>">

      <div class="mt-4 d-flex gap-2">
        <button type="submit" name="update_client" style="color: white;" class="btn btn-warning">
          Update Client
        </button>

        <a href="clientinfo.php" class="btn btn-primary">
          Back
        </a>
      </div>

    </form>
  <?php endif; ?>

  <?php if ($viewing && $view_data): ?>
    <div class="my-4" style="width: 95%; margin: auto;">
      <div class="card border-0 rounded-3 bg-light">
        <div class="card-header bg-light text-black d-flex align-items-center">
          <div class="d-flex align-items-center">
            <?php session_abort() ?>
            <?php include('../components/Backbtn.php') ?>
            <div class="d-flex align-items-center">
              <i class="bi bi-eye-fill me-2" style="font-size: 1.5rem;"></i>
              <h4 class="mb-0">Viewing Client: <?= htmlspecialchars($view_data['client_name']) ?></h4>
            </div>
          </div>
        </div>

        <div class="card-body">
          <div class="row g-6">
            <div class="col-md-6">
              <div class="d-flex flex-column gap-2">
                <p class="mb-0"><strong>Name:</strong> <?= htmlspecialchars($view_data['client_name']) ?></p>
                <p class="mb-0"><strong>Email:</strong> <?= htmlspecialchars($view_data['email']) ?></p>
                <p class="mb-0"><strong>Contact Person:</strong> <?= htmlspecialchars($view_data['contact_person']) ?></p>
                <p class="mb-0"><strong>Mobile Number:</strong> <?= htmlspecialchars($view_data['number']) ?></p>
                <p class="mb-0"><strong>Office Number:</strong> <?= htmlspecialchars($view_data['office_number']) ?></p>
                <p class="mb-0"><strong>Accounts Contact:</strong> <?= htmlspecialchars($view_data['accounts_contact']) ?>
                </p>
                <p class="mb-0"><strong>Accounts Email:</strong> <?= htmlspecialchars($view_data['accounts_email']) ?></p>
                <p class="mb-0"><strong>Address:</strong> <?= htmlspecialchars($view_data['address']) ?></p>
              </div>
            </div>

            <div class="col-md-6">
              <div class="d-flex flex-column gap-2">
                <p class="mb-1"><strong>Status:</strong>
                  <span class="badge bg-<?= $view_data['status'] == 'Active' ? 'success' : 'secondary' ?>">
                    <?= ucfirst(htmlspecialchars($view_data['status'])) ?>
                  </span>
                </p>
                <p class="mb-1"><strong>Billing Type:</strong> <?= htmlspecialchars($view_data['billing_type']) ?></p>
                <p class="mb-1"><strong>Billing Country:</strong> <?= htmlspecialchars($view_data['billing_country']) ?>
                </p>
                <p class="mb-1"><strong>Currency:</strong> <?= htmlspecialchars($view_data['currency']) ?></p>
                <p class="mb-1"><strong>VAT Number:</strong> <?= htmlspecialchars($view_data['vat_number']) ?></p>
                <p class="mb-1"><strong>Registration Number:</strong>
                  <?= htmlspecialchars($view_data['registration_number']) ?></p>
                <p class="mb-1"><strong>Sales Person:</strong> <?= htmlspecialchars($view_data['sales_person']) ?></p>
                <p class="mb-1"><strong>Created At:</strong> <?= htmlspecialchars($view_data['created_at']) ?></p>
              </div>
            </div>

            <div class="col-12">
              <p><strong>Notes:</strong><br><?= nl2br(htmlspecialchars($view_data['notes'])) ?></p>
            </div>
            <?php if (isset($_GET['view']) || isset($_GET['edit'])): ?>
              <div class="card-footer bg- border-0">
                <div class="d-flex flex-wrap gap-2">
                  <a href="Exchange_Mail.php?client_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-envelope-fill me-1"></i> Exchange
                  </a>
                  <a href="client_365.php?client_id=<?= $id ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-cloud-fill me-1"></i> 365
                  </a>
                  <a href="client_support.php?client_id=<?= $id ?>" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-tools me-1"></i> Support Info
                  </a>
                  <a href="client_documents.php?client_id=<?= $id ?>" class="btn btn-sm btn-outline-dark">
                    <i class="bi bi-folder-fill me-1"></i> Documents
                  </a>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Trigger Button -->
  <div class="d-flex justify-content-between align-items-center mt-5 mb-4" style="width: 95%; margin: auto;">
    <h3 class="mb-0 d-flex align-items-center">
      <i class="bi bi-people-fill me-2 text-secondary" style="font-size: 1.5rem;"></i>
      <span class="fw-semibold text-dark">Clients</span>
    </h3>
    <!-- Trigger Button -->
    <?php if (hasPermission('clients', 'create')): ?>
      <button id="showClientForm" class="btn btn-sm btn-success">
        Add New Client
      </button>
    <?php endif; ?>
  </div>
  <!-- <button id="showClientForm">➕ Add New Client</button> -->
  <!-- Modal Structure -->
  <div id="clientModal" class="modal" style="display:none;">
    <div class="modal-content position-relative p-4 rounded bg-white" style="max-width: 900px; margin: 2rem auto;">

      <!-- Cross Button -->
      <button type="button" class="btn-close position-absolute top-0 end-0 m-3" aria-label="Close"
        id="closeClientModal"></button>

      <!-- Form Starts -->
      <form method="post" class="container p-0">
        <h2 class="mb-4 text-success">Add New Client</h2>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Client Name</label>
            <input type="text" name="client_name" class="form-control" placeholder="Client Name" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Mobile</label>
            <input type="number" name="number" class="form-control" placeholder="Client Number" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="text" name="email" class="form-control" placeholder="Email">
          </div>
          <div class="col-md-6">
            <label class="form-label">Contact Person</label>
            <input type="text" name="contact_person" class="form-control" placeholder="Contact Person">
          </div>
          <div class="col-md-6">
            <label class="form-label">Office Number</label>
            <input type="text" name="office_number" class="form-control" placeholder="Office Number">
          </div>
          <div class="col-md-6">
            <label class="form-label">Address</label>
            <input type="text" name="address" class="form-control" placeholder="Address">
          </div>
          <div class="col-md-6">
            <label class="form-label">Accounts Contact</label>
            <input type="text" name="accounts_contact" class="form-control" placeholder="Accounts Contact">
          </div>
          <div class="col-md-6">
            <label class="form-label">Accounts Email</label>
            <input type="text" name="accounts_email" class="form-control" placeholder="Accounts Email">
          </div>
          <div class="col-md-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" placeholder="Notes" rows="3"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">VAT Number</label>
            <input type="text" name="vat_number" class="form-control" placeholder="VAT Number">
          </div>
          <div class="col-md-6">
            <label class="form-label">Registration Number</label>
            <input type="text" name="registration_number" class="form-control"
              placeholder="Company Registration Number">
          </div>
          <div class="col-md-6">
            <label class="form-label">Billing Type</label>
            <select name="billing_type" class="form-select">
              <option value="Invoice">Invoice</option>
              <option value="Debit Order">Debit Order</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="Active">Active</option>
              <option value="Suspended">Suspended</option>
              <option value="Cancelled">Cancelled</option>
              <option value="Lead">Lead</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Sales Person</label>
            <input type="text" name="sales_person" class="form-control" placeholder="Sales Person">
          </div>
          <div class="col-md-6">
            <label class="form-label">Billing Country</label>
            <select name="billing_country" class="form-select">
              <option value="RSA">RSA</option>
              <option value="Namibia">Namibia</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Currency</label>
            <input type="text" name="currency" class="form-control" placeholder="Currency">
          </div>
          <div class="col-md-6">
            <label class="form-label">Currency Symbol</label>
            <input type="text" name="currency_symbol" class="form-control">
          </div>
        </div>

        <div class="mt-4">
          <button type="submit" name="save_client" class="btn btn-success">
            Save New Client
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Script to handle modal close -->
  <script>
    document.getElementById('closeClientModal').addEventListener('click', function () {
      document.getElementById('clientModal').style.display = 'none';
    });
  </script>


  <script>
    document.getElementById("showClientForm").addEventListener("click", function () {
      document.querySelector(".client-form").style.display = "block";
    });
  </script>

  <div class="" style="width: 95%; margin: auto;">
    <!-- Script to handle modal close -->
    <script>
      document.getElementById('closeClientModal').addEventListener('click', function () {
        document.getElementById('clientModal').style.display = 'none';
      });
    </script>


    <script>
      document.getElementById("showClientForm").addEventListener("click", function () {
        document.querySelector(".client-form").style.display = "block";
      });
    </script>

    <div class="" style="width: 95%; margin: auto;">
      <table class="table table-hover table-bordered table-striped align-middle shadow-sm rounded bg-white">
        <thead class="table-light text-center">
          <tr>
            <th scope="col">#</th>
            <th scope="col">Client</th>
            <th scope="col">Contact</th>
            <th scope="col">Status</th>
            <th scope="col">Created</th>
            <th scope="col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          if ($viewing || $editing) {
            $clientResult = $conn->query("SELECT * FROM clients WHERE id = $id");
          } else {
            $clientResult = $conn->query("SELECT * FROM clients");
          }
          $i = 1;
          while ($row = $clientResult->fetch_assoc()):
            $status = strtolower($row['status']);
            $statusClass = match ($status) {
              'active' => 'success',
              'suspended' => 'warning',
              'lead' => 'info',
              'cancelled' => 'danger',
              default => 'secondary',
            };
            ?>
            <tr>
              <td class="text-center"><?= htmlspecialchars($i) ?></td>
              <td><?= htmlspecialchars($row['client_name']) ?></td>
              <td><?= htmlspecialchars($row['contact_person']) ?></td>
              <td class="">
                <span class="badge bg-<?= $statusClass ?> px-3 py-2">
                  <?= ucfirst(htmlspecialchars($row['status'])) ?>
                </span>
              </td>
              <td><?= htmlspecialchars(date('d M Y', strtotime($row['created_at']))) ?></td>
              <td class="text-center">
                <div class="btn-group" role="group" aria-label="Actions">
                  <a href="?view=<?= $row['id'] ?>" class="btn btn-sm" title="View">
                    <i class="fas fa-eye"></i>
                  </a>
                  <?php if (hasPermission('clients', 'update')): ?>
                    <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm " title="Edit">
                      <i class="fas fa-edit"></i>
                    </a>
                  <?php endif; ?>
                  <?php if (hasPermission('clients', 'delete')): ?>
                    <a href="?delete_client=<?= $row['id'] ?>" onclick="return confirm('Delete this client?')"
                      class="btn btn-sm text-danger" title="Delete">
                      <i class="fas fa-trash-alt"></i>
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php $i++;
          endwhile; ?>
        </tbody>
      </table>
    </div>


    <?php if ($viewing || $editing): ?>
    </div>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const modal = document.getElementById("clientModal");
    const openBtn = document.getElementById("showClientForm");
    const closeBtn = document.querySelector(".modal .close");

    openBtn.onclick = () => {
      modal.style.display = "block";
    }

    closeBtn.onclick = () => {
      modal.style.display = "none";
    }

    window.onclick = (e) => {
      if (e.target == modal) {
        modal.style.display = "none";
      }
    }
  </script>

</html>


<script>
  document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("exchangeForm");
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      const data = new FormData(form);
      fetch('/modules/clientinfo/save_exchange.php', {
        method: 'POST',
        body: data
      })
        .then(res => res.text())
        .then(response => {
          const statusBox = document.getElementById('exchange_status');
          statusBox.innerText = response;
          statusBox.style.color = 'green';
          form.reset();
          document.getElementById("domain").value = lastDomain;
          statusBox.scrollIntoView({
            behavior: 'smooth'
          });
        })
        .catch(() => {
          const statusBox = document.getElementById('exchange_status');
          statusBox.innerText = '❌ Error saving entry.';
          statusBox.style.color = 'red';
        });
    });
  });
</script>