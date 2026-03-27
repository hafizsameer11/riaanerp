<?php
// Start session for flash messages
session_start();

// Database connection
include_once '../config.php';

// Handle flash messages from redirects
$alert = $_SESSION['wip_alert'] ?? null;
$message = $_SESSION['wip_message'] ?? null;
unset($_SESSION['wip_alert']);
unset($_SESSION['wip_message']);

// Handle Add WIP
if (isset($_POST['add_wip'])) {
  $client = $_POST['client'];
  $quote = $_POST['quote'];
  $sales = $_POST['sales'];
  $desc = $_POST['description'];
  $note = $_POST['note'] ?? '';
  $terms = $_POST['terms'];
  $price = $_POST['price'];
  $status = $_POST['status'];
  
  $stmt = $conn->prepare("INSERT INTO wip (client_id, quote_id, sales_person, description, note, terms, monthly_price_incl_vat, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("isssssds", $client, $quote, $sales, $desc, $note, $terms, $price, $status);
  if ($stmt->execute()) {
    $_SESSION['wip_alert'] = "success";
    $_SESSION['wip_message'] = "WIP entry added successfully!";
  } else {
    $_SESSION['wip_alert'] = "danger";
    $_SESSION['wip_message'] = "Error adding WIP entry: " . $stmt->error;
  }
  $stmt->close();
  header("Location: wip.php");
  exit();
}

// Handle Edit WIP
if (isset($_POST['edit_wip'])) {
  $id = $_POST['id'];
  $client = $_POST['client'];
  $quote = $_POST['quote'];
  $sales = $_POST['sales'];
  $desc = $_POST['description'];
  $note = $_POST['note'] ?? '';
  $terms = $_POST['terms'];
  $price = $_POST['price'];
  $status = $_POST['status'];

  $stmt = $conn->prepare("UPDATE wip SET client_id=?, quote_id=?, sales_person=?, description=?, note=?, terms=?, monthly_price_incl_vat=?, status=? WHERE id=?");
  $stmt->bind_param("isssssdsi", $client, $quote, $sales, $desc, $note, $terms, $price, $status, $id);
  if ($stmt->execute()) {
    $_SESSION['wip_alert'] = "success";
    $_SESSION['wip_message'] = "WIP entry updated successfully!";
  } else {
    $_SESSION['wip_alert'] = "danger";
    $_SESSION['wip_message'] = "Error updating WIP entry: " . $stmt->error;
  }
  $stmt->close();
  header("Location: wip.php");
  exit();
}

// Handle Delete WIP
if (isset($_POST['delete_wip'])) {
  $id = intval($_POST['delete_id']);
  $stmt = $conn->prepare("DELETE FROM wip WHERE id = ?");
  $stmt->bind_param("i", $id);
  if ($stmt->execute()) {
    $_SESSION['wip_alert'] = "success";
    $_SESSION['wip_message'] = "WIP entry deleted successfully!";
  } else {
    $_SESSION['wip_alert'] = "danger";
    $_SESSION['wip_message'] = "Error deleting WIP entry: " . $stmt->error;
  }
  $stmt->close();
  header("Location: wip.php");
  exit();
}

// Fetch Clients and Quotes
$clients = $conn->query("SELECT * FROM clients ORDER BY client_name ASC");
$quotes = $conn->query("SELECT id, quote_number FROM quotes");
?>

<!DOCTYPE html>
<html>

<head>
  <title>WIP Module</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body class="p-4">

  <div class="py-5">
      <div class="d-flex align-items-center justify-content-between mb-3">
      <div class="d-flex align-items-center">
        <?php include('../components/permissioncheck.php') ?>
        <h2 class="">Work In Progress</h2>
      </div>
      <div class="d-flex gap-2">
        <!-- Export Button -->
        <a href="export-wip.php?<?= http_build_query($_GET) ?>" class="btn btn-primary mb-3">
          <i class="fas fa-file-excel"></i> Export to Excel
        </a>
        <?php if (hasPermission('wip', 'create')): ?>
          <!-- Add Button -->
          <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addModal">+ Add WIP</button>
        <?php endif; ?>
      </div>
    </div>
    <!-- Alerts -->
    <?php if (isset($alert) && isset($message)): ?>
      <div class="alert alert-<?= $alert ?> alert-dismissible fade show" role="alert">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>


    <!-- Filter Form -->
    <form class="card shadow-sm p-4 my-4" method="GET">
      <div class="row g-3 align-items-end">
        <!-- General Search -->
        <div class="col-md-3">
          <label class="form-label">General Search</label>
          <input type="text" name="search" class="form-control" 
                 placeholder="Search Client, Quote, Sales Person, Description, Status, or Note..."
                 value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
        </div>

        <!-- Client Filter -->
        <div class="col-md-3">
          <label class="form-label">Client</label>
          <select name="client_id" class="form-select">
            <option value="">All Clients</option>
            <?php
            $clients->data_seek(0); // Reset pointer for reuse
            while ($client = $clients->fetch_assoc()): ?>
              <option value="<?= $client['id'] ?>" <?= isset($_GET['client_id']) && $_GET['client_id'] == $client['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($client['client_name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <!-- Quote Filter -->
        <div class="col-md-3">
          <label class="form-label">Quote</label>
          <input type="text" name="quote_id" class='form-control' placeholder="Quote"
            value="<?= htmlspecialchars($_GET['quote_id'] ?? '') ?>">
        </div>

        <!-- Status Filter -->
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <option value="Quoted" <?= ($_GET['status'] ?? '') === 'Quoted' ? 'selected' : '' ?>>Quoted</option>
            <option value="Followed up" <?= ($_GET['status'] ?? '') === 'Followed up' ? 'selected' : '' ?>>Followed up</option>
            <option value="Declined" <?= ($_GET['status'] ?? '') === 'Declined' ? 'selected' : '' ?>>Declined</option>
            <option value="Approved" <?= ($_GET['status'] ?? '') === 'Approved' ? 'selected' : '' ?>>Approved</option>
            <option value="Monthly Billing" <?= ($_GET['status'] ?? '') === 'Monthly Billing' ? 'selected' : '' ?>>Added to Monthly Billing</option>
            <option value="Invoiced" <?= ($_GET['status'] ?? '') === 'Invoiced' ? 'selected' : '' ?>>Invoiced</option>
          </select>
        </div>
            
      
        <!-- Apply and Reset Buttons -->
        <div class="col-12 text-end">
          <button type="submit" class="btn btn-primary">Apply Filters</button>
          <a href="wip.php" class="btn btn-secondary">Reset</a>
        </div>
      </div>
    </form>

    <!-- Table -->
    <table class="table table-bordered">
      <thead>
        <tr>
          <th>ID</th>
          <th>Client</th>
          <th>Quote #</th>
          <th>Sales Person</th>
          <th>Description</th>
          <th>Note</th>
          <th>Price</th>
          <th>Terms</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <?php
      $where = [];
      
      // General search - searches across multiple fields
      if (!empty($_GET['search'])) {
        $searchTerm = $conn->real_escape_string($_GET['search']);
        $searchConditions = [
          "c.client_name LIKE '%$searchTerm%'",
          "w.quote_id LIKE '%$searchTerm%'",
          "w.sales_person LIKE '%$searchTerm%'",
          "w.description LIKE '%$searchTerm%'",
          "w.status LIKE '%$searchTerm%'",
          "w.note LIKE '%$searchTerm%'"
        ];
        $where[] = "(" . implode(' OR ', $searchConditions) . ")";
      }
      
      // Specific filters (can be combined with general search)
      if (!empty($_GET['client_id'])) {
        $where[] = "w.client_id = " . (int) $_GET['client_id'];
      }
      if (!empty($_GET['quote_id'])) {
        $where[] = "w.quote_id = " . (int) $_GET['quote_id'];
      }
      if (!empty($_GET['status'])) {
        $where[] = "w.status = '" . $conn->real_escape_string($_GET['status']) . "'";
      }
      if (!empty($_GET['note'])) {
        $note = $conn->real_escape_string($_GET['note']);
        $where[] = "w.note LIKE '%$note%'";
      }

      $filterSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

      $res = $conn->query("
            SELECT w.*, c.client_name, c.currency, c.currency_symbol
            FROM wip w
            LEFT JOIN clients c ON w.client_id = c.id
            $filterSql
            ORDER BY c.client_name ASC
        ");
      ?>
      <tbody>
        <?php
        $i = 1;
        while ($row = $res->fetch_assoc()):

          ?>
          <tr>
            <td class="text-center"><?= $i++ ?></td>
            <td><?= htmlspecialchars($row['client_name']) ?></td>
            <td><?= htmlspecialchars($row['quote_id']) ?></td>
            <td><?= htmlspecialchars($row['sales_person']) ?></td>
            <td><?= htmlspecialchars($row['description']) ?></td>
            <td><?= htmlspecialchars($row['note']) ?></td>
            <td class="text-end">
              <?= $row['currency_symbol'] ? $row['currency_symbol'] : (isset($row['currency'][0]) ? $row['currency'][0] : '') ?> 
              <?= number_format($row['monthly_price_incl_vat'], 2) ?>
            </td>
            <td class="text-center"><?= htmlspecialchars($row['terms']) ?></td>
            <td class="text-center"><?= htmlspecialchars($row['status']) ?></td>
            <td class="text-center">
              <div class="btn-group" role="group" aria-label="Actions">
                <button class="btn btn-sm text-info" data-bs-toggle="modal" data-bs-target="#viewWipModal"
                  data-id="<?= $row['id'] ?>" data-client="<?= htmlspecialchars($row['client_name']) ?>"
                  data-quote="<?= htmlspecialchars($row['quote_id']) ?>"
                  data-sales="<?= htmlspecialchars($row['sales_person']) ?>"
                  data-description="<?= htmlspecialchars($row['description']) ?>"
                  data-note="<?= htmlspecialchars($row['note']) ?>"
                  data-price="<?= $row['currency_symbol'] ?> <?= $row['monthly_price_incl_vat'] ?>"
                  data-status="<?= htmlspecialchars($row['status']) ?>">
                  <i class="fas fa-eye"></i> View
                </button>

                <?php if (hasPermission('wip', 'update')): ?>
                  <button class="btn btn-sm btn-edit-wip" 
                    data-id="<?= $row['id'] ?>"
                    data-client-id="<?= $row['client_id'] ?>"
                    data-quote="<?= htmlspecialchars($row['quote_id'], ENT_QUOTES) ?>"
                    data-sales="<?= htmlspecialchars($row['sales_person'], ENT_QUOTES) ?>"
                    data-description="<?= htmlspecialchars($row['description'], ENT_QUOTES) ?>"
                    data-note="<?= htmlspecialchars($row['note'], ENT_QUOTES) ?>"
                    data-price="<?= $row['monthly_price_incl_vat'] ?>"
                    data-terms="<?= htmlspecialchars($row['terms'], ENT_QUOTES) ?>"
                    data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES) ?>"
                    title="Edit">
                    <i class="fas fa-edit"></i> Edit
                  </button>
                <?php endif; ?>

                <?php if (hasPermission('wip', 'delete')): ?>
                  <button class="btn btn-sm text-danger" onclick='loadDelete(<?= $row['id'] ?>)' title="Delete">
                    <i class="fas fa-trash-alt"></i> Delete
                  </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>

    </table>
  </div>

  <!-- Add Modal -->
  <div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="POST" class="modal-content">
        <div class="modal-header">
          <h5>Add WIP</h5>
        </div>
        <div class="modal-body">
          <select name="client" id="add-client"   class="form-control mb-2" required>
            <option value="" disabled selected>Select Client</option>
            <?php
            $clients->data_seek(0);
            while ($client = $clients->fetch_assoc()): ?>
              <option value="<?= $client['id'] ?>"><?= $client['client_name'] ?></option>
            <?php endwhile; ?>
          </select>
          <Input type="text" name="quote" class="form-control mb-2" placeholder="Quote" required>
          <input type="text" name="sales" class="form-control mb-2" placeholder="Sales Person" required>
          <textarea name="description" class="form-control mb-2" placeholder="Description"></textarea>
          <textarea name="note" class="form-control mb-2" placeholder="Note"></textarea>
          <select name="terms" class="form-control mb-2" required>
            <!-- <option value="" disabled selected>Terms</option> -->
            <option value="once_off">Once off</option>
            <option value="monthly">Monthly</option>
            <option value="annually">Annually</option>
          </select>

          <input type="number" name="price" step="0.01" class="form-control mb-2" placeholder="Price incl VAT">
          <input type="text" id="add-currencey" class="form-control mb-2" placeholder="Currency"  readonly>
          <input type="text" id="add-currencey-symbol" class="form-control mb-2" placeholder="Currency Symbol" readonly>
          <select name="status" class="form-control mb-2" required>
            <!-- <option disabled selected>status</option> -->
            <option value="Quoted">Quoted</option>
            <option value="Followed up">Followed up</option>
            <option value="Declined">Declined</option>
            <option value="Approved">Approved</option>
            <option value="Monthly Billing">Added to Monthly Billing</option>
            <option value="Invoiced">Invoiced</option>
          </select>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_wip" class="btn btn-success">Add</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="POST" class="modal-content">
        <div class="modal-header">
          <h5>Edit WIP</h5>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="edit_id">
          <select name="client" id="edit_client" class="form-control mb-2" required>
            <option value="" disabled>Select Client</option>
            <?php
            $clients->data_seek(0); // Reset pointer for reuse
            while ($client = $clients->fetch_assoc()): ?>
              <option value="<?= $client['id'] ?>"><?= $client['client_name'] ?></option>
            <?php endwhile; ?>
          </select>
          <input type="text" name="quote" id="edit_quote" class="form-control mb-2" required>
          <input type="text" name="sales" id="edit_sales" class="form-control mb-2" required>
          <textarea name="description" id="edit_description" class="form-control mb-2"></textarea>
          <textarea name="note" id="edit_note" class="form-control mb-2" placeholder="Note"></textarea>
          <select name="terms" id="edit_terms" class="form-control mb-2" required>
            <option value="once_off">Once off</option>
            <option value="monthly">Monthly</option>
            <option value="annually">Annually</option>
          </select>

          <input type="number" name="price" id="edit_price" step="0.01" class="form-control mb-2">
          <input type="text" id="edit-currencey" class="form-control mb-2" placeholder="Currency" readonly>
          <input type="text" id="edit-currencey-symbol" class="form-control mb-2" placeholder="Currency Symbol" readonly>
          <select name="status" id="edit_status" class="form-control mb-2">
            <option value="Quoted">Quoted</option>
            <option value="Followed up">Followed up</option>
            <option value="Declined">Declined</option>
            <option value="Approved">Approved</option>
            <option value="Monthly Billing">Added to Monthly Billing</option>
            <option value="Invoiced">Invoiced</option>

          </select>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_wip" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="POST" class="modal-content">
        <div class="modal-header">
          <h5>Delete Confirmation</h5>
        </div>
        <div class="modal-body">
          Are you sure you want to delete this WIP entry?
          <input type="hidden" name="delete_id" id="delete_id">
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">No</button>
          <button type="submit" name="delete_wip" class="btn btn-danger">Yes, Delete</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="viewWipModal" tabindex="-1" aria-labelledby="viewWipModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="viewWipModalLabel">WIP Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p><strong>ID:</strong> <span id="view-id"></span></p>
          <p><strong>Client:</strong> <span id="view-client"></span></p>
          <p><strong>Quote:</strong> <span id="view-quote"></span></p>
          <p><strong>Sales Person:</strong> <span id="view-sales"></span></p>
          <p><strong>Description:</strong> <span id="view-description"></span></p>
          <p><strong>Note:</strong> <span id="view-note"></span></p>
          <!-- <p><strong>Terms:</strong> <span id="view-terms"></span></p> -->
          <p><strong>Price incl VAT:</strong> <span id="view-price"></span></p>
          <p><strong>Status:</strong> <span id="view-status"></span></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    const clients = <?= json_encode(iterator_to_array($clients, true)) ?>;
    
    // Handle edit button clicks using data attributes
    document.querySelectorAll('.btn-edit-wip').forEach(function(button) {
      button.addEventListener('click', function() {
        const clientId = this.getAttribute('data-client-id');
        $('#edit_id').val(this.getAttribute('data-id'));
        $('#edit_client').val(clientId);
        $('#edit_quote').val(this.getAttribute('data-quote'));
        $('#edit_sales').val(this.getAttribute('data-sales'));
        $('#edit_description').val(this.getAttribute('data-description'));
        $('#edit_note').val(this.getAttribute('data-note'));
        $('#edit_price').val(this.getAttribute('data-price'));
        $('#edit_status').val(this.getAttribute('data-status'));
        $('#edit_terms').val(this.getAttribute('data-terms'));
        
        const selectedClient = clients.find(client => client.id == clientId);
        if (selectedClient) {
          document.getElementById('edit-currencey').value = selectedClient.currency || '';
          document.getElementById('edit-currencey-symbol').value = selectedClient.currency_symbol || '';
        }
        new bootstrap.Modal(document.getElementById('editModal')).show();
      });
    });
    document.getElementById('add-client').addEventListener('change', function () {
      const selectedClient = clients.find(client => client.id == this.value);
      if (selectedClient) {
        document.getElementById('add-currencey').value = selectedClient.currency;
        document.getElementById('add-currencey-symbol').value = selectedClient.currency_symbol;
      }
    });
    document.getElementById('edit_client').addEventListener('change', function () {
      const selectedClient = clients.find(client => client.id == this.value);
      if (selectedClient) {
        document.getElementById('edit-currencey').value = selectedClient.currency;
        document.getElementById('edit-currencey-symbol').value = selectedClient.currency_symbol;
      }
    });

    

    function loadDelete(id) {
      $('#delete_id').val(id);
      new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    document.querySelectorAll('[data-bs-target="#viewWipModal"]').forEach(button => {
      button.addEventListener('click', () => {
        // document.getElementById('view-terms').textContent = button.getAttribute('data-terms');
        document.getElementById('view-id').textContent = button.getAttribute('data-id');
        document.getElementById('view-client').textContent = button.getAttribute('data-client');
        document.getElementById('view-quote').textContent = button.getAttribute('data-quote');
        document.getElementById('view-sales').textContent = button.getAttribute('data-sales');
        document.getElementById('view-description').textContent = button.getAttribute('data-description');
        document.getElementById('view-note').textContent = button.getAttribute('data-note');
        document.getElementById('view-price').textContent = button.getAttribute('data-price');
        document.getElementById('view-status').textContent = button.getAttribute('data-status');
      });
    });
  </script>

</body>

</html>