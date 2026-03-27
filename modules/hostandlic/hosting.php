<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Live server settings
$db_host = "localhost";
$db_user = "clientzone_user";
$db_pass = "S@utech2024!";
$db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct

if ($conn->connect_error)
  die("Connection failed: " . $conn->connect_error);

// Tables
$columns = ['client_name', 'location', 'asset_type', 'host', 'server_name',  'spla'];

// Insert Hosting Record
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_hosting"])) {
  $fields = $columns;
  $data = [];
  foreach ($fields as $f)
    $data[$f] = $conn->real_escape_string($_POST[$f] ?? '');
  $stmt = $conn->prepare("INSERT INTO hosting_assets (" . implode(',', $fields) . ") VALUES (" . str_repeat('?,', count($fields) - 1) . "?)");
  $stmt->bind_param(str_repeat("s", count($fields)), ...array_values($data));
  $stmt->execute();
}

// Add/Delete Support Values
foreach (["location", "asset_type", "host", "os"] as $type) {
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["add_$type"])) {
      $val = $conn->real_escape_string($_POST["new_$type"]);
      $conn->query("INSERT IGNORE INTO hosting_{$type}s (name) VALUES ('$val')");
    }
    if (isset($_POST["delete_$type"])) {
      $val = $conn->real_escape_string($_POST["remove_$type"]);
      $conn->query("DELETE FROM hosting_{$type}s WHERE name = '$val'");
    }
  }
}

// Delete Hosting Record
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_hosting_record"])) {
  $id = intval($_POST["hosting_record_id"]);
  $conn->query("DELETE FROM hosting_assets WHERE id = $id");
}

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment;filename=hosting_export.csv');
  $out = fopen("php://output", "w");
  fputcsv($out, $columns);
  $res = $conn->query("SELECT * FROM hosting_assets");
  while ($r = $res->fetch_assoc())
    fputcsv($out, array_values($r));
  fclose($out);
  exit;
}

// Filters
$filters = [];
$where = [];
foreach ($columns as $col) {
  $res = $conn->query("SELECT DISTINCT $col FROM hosting_assets ORDER BY $col");
  $filters[$col] = [];
  while ($r = $res->fetch_assoc())
    $filters[$col][] = $r[$col];
  if (!empty($_GET[$col]))
    $where[] = "$col = '" . $conn->real_escape_string($_GET[$col]) . "'";
}
$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";
$data = $conn->query("SELECT * FROM hosting_assets $whereSQL");

// Support dropdowns

$support_tables = ['location' => 'hosting_locations', 'asset_type' => 'hosting_asset_types', 'host' => 'hosting_hosts', 'os' => 'hosting_oss'];
$support = [];
foreach ($support_tables as $key => $table) {
  $support[$key] = [];
  $q = $conn->query("SELECT name FROM $table ORDER BY name ASC");
  if ($q) {
    while ($r = $q->fetch_assoc()) {
      $support[$key][] = $r['name'];
    }
  } else {
    echo "<div style='color:red;'>⚠️ Query failed for table: $table – " . $conn->error . "</div>";
  }
}

$clients = [];
$cl = $conn->query("SELECT id, client_name FROM clients ORDER BY client_name ASC");
while ($r = $cl->fetch_assoc())
  $clients[] = $r;

$records = $conn->query("SELECT id, client_name, server_name FROM hosting_assets ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Hosting Manager</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Custom Hosting CSS -->
  <link href="../../assets/css/hosting.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body>

  <div  style="width: 93%; margin: auto; min-height: 100vh; overflow: auto;padding-block:50px;">
    <h3 class="text-dark mb-4 d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <?php session_start(); ?>
        <?php include('../components/permissioncheck.php') ?>
        <span class="ml-2">Hosting</span>
      </div>
      <!-- <a href="login/register.php" class="btn btn-primary">Logins</a> -->
    </h3>
    <div id="alertBox" class="alert d-none mt-3" role="alert"></div>

    <!-- Add Hosting Record -->
    <?php if (hasPermission('hosting', 'create')): ?>
      <div class="card p-4 shadow-sm mb-5">
        <h4 class="text-success mb-4">Add Hosting Record</h4>
        <form method="POST" id="Editform" class="row g-4">
          <input type="hidden" name="hosting_record_id" id="form_mode_id">

          <div class="col-md-4">
            <label for="client_name" class="form-label">Client Name</label>
            <select id="client_name" name="client_name" class="form-select" onchange="updateClientId(this)">
              <option value="">Select Client</option>
              <?php foreach ($clients as $client): ?>
                <option value="<?= htmlspecialchars($client['client_name']) ?>" data-client-id="<?= $client['id'] ?>">
                  <?= htmlspecialchars($client['client_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label for="client_id" class="form-label">Client ID</label>
            <input type="text" id="client_id" readonly name="client_id" class="form-control">
          </div>
          <div class="col-md-4">
            <label for="location" class="form-label">Location</label>
            <select name="location" id="location" class="form-select">
              <option value="">-- Select Location --</option>
              <?php foreach ($support['location'] as $location): ?>
                <option value="<?= htmlspecialchars($location) ?>">
                  <?= htmlspecialchars($location) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label for="asset_type" class="form-label">Asset Type</label>
            <select name="asset_type" id="asset_type" class="form-select" onchange="toggleFieldsBasedOnOS(this)">
              <option value="">-- Select Asset Type --</option>
              <?php foreach ($support['asset_type'] as $location): ?>
                <option value="<?= htmlspecialchars($location) ?>">
                  <?= htmlspecialchars($location) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4 vm-field" style='display:none'>
            <label for="os" class="form-label">OS</label>
            <select id="os" name="os" class="form-select">
              <option value="">Select OS</option>
              <?php foreach ($support['os'] as $location): ?>
                <option value="<?= htmlspecialchars($location) ?>">
                  <?= htmlspecialchars($location) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <!-- VM-Specific Fields -->
          <div class="col-md-4 vm-field" style="display: none;">
            <label for="host" class="form-label">Host</label>
            <!-- <input type="text" id="host" name="host" class="form-control"> -->
            <select id="host" name="host" class="form-select">
              <option value="">Select Host</option>
              <?php foreach ($support['host'] as $location): ?>
                <option value="<?= htmlspecialchars($location) ?>">
                  <?= htmlspecialchars($location) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 vm-field" style="display: none;">
            <label for="server_name" class="form-label">Server Name</label>
            <input type="text" id="server_name" name="server_name" class="form-control">
          </div>
          <div class="col-md-4 vm-field" style="display: none;">
            <label for="cpu" class="form-label">CPU</label>
            <input type="text" id="cpu" name="cpu" class="form-control">
          </div>
          <div class="col-md-4 vm-field" style="display: none;">
            <label for="ram" class="form-label">RAM</label>
            <input type="text" id="ram" name="ram" class="form-control">
          </div>
          <div class="col-md-4 vm-field" style="display: none;">
            <label for="sata" class="form-label">HDD SATA</label>
            <input type="text" id="sata" name="sata" class="form-control">
          </div>
          <div class="col-md-4 vm-field" style="display: none;">
            <label for="ssd" class="form-label">HDD SSD</label>
            <input type="text" id="ssd" name="ssd" class="form-control">
          </div>
          <div class="col-md-4 ">
            <label for="private_ip" class="form-label">Private IP</label>
            <input type="text" id="private_ip" name="private_ip" class="form-control">
          </div>
          <div class="col-md-4">
            <label for="public_ip" class="form-label">Public IP</label>
            <input type="text" id="public_ip" name="public_ip" class="form-control">
          </div>
          <div class="col-md-4">
            <label for="username" class="form-label">Username</label>
            <input type="text" id="username" name="username" class="form-control">
          </div>
          <div class="col-md-4">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password" class="form-control">
          </div>
          <div class="col-md-4">
            <label for="spla" class="form-label">SPLA</label>
            <select id="spla" name="spla" class="form-select">
              <option value="No">No</option>
              <option value="Yes">Yes</option>
            </select>
          </div>
          <div class="col-md-4">
            <label for="login_url" class="form-label">Login URL</label>
            <input type="text" id="login_url" name="login_url" class="form-control">
          </div>
          <div class="col-md-4">
            <label for="note" class="form-label">Note</label>
            <textarea id="note" name="note" class="form-control" placeholder="Note"></textarea>
          </div>

          <div class="col-12 text-start">
            <button type="submit" name="submit_hosting" class="btn btn-success" id="form_submit_btn">
              Add Hosting Record
            </button>

          </div>
        </form>
      </div>
    <?php endif; ?>
    <?php if (hasPermission('hosting', 'update')): ?>
      <!-- Delete Hosting Record -->
      <div class="card p-4 shadow-sm mb-5">
        <h4 class="text-danger mb-4">Delete Hosting Record</h4>
        <form method="POST" class="row g-3 align-items-center">
          <div class="col-md-12">
            <select name="hosting_record_id" class="form-select">
              <option value="">Select Hosting Record</option>
              <?php while ($r = $records->fetch_assoc()): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['client_name']) ?> –
                  <?= htmlspecialchars($r['server_name']) ?> (ID: <?= $r['id'] ?>)
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-3">
            <button type="submit" name="delete_hosting_record" class="btn btn-danger w-100"> Delete Hosting
              Record</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <!-- Filters Section -->
    <div class="card p-4 shadow-sm mb-5">
      <h4 class="text-primary mb-4">Filters</h4>
      <form method="GET" class="row g-4">
        <?php foreach ($filters as $col => $vals): ?>
          <div class="col-md-3">
            <select name="<?= $col ?>" class="form-select">
              <option value="">Filter <?= ucwords(str_replace('_', ' ', $col)) ?></option>
              <?php foreach ($vals as $v): ?>
                <option value="<?= $v ?>" <?= isset($_GET[$col]) && $_GET[$col] === $v ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endforeach; ?>
        <div class="col-md-12 text-start">
          <button class="btn btn-primary">Apply Filters</button>
          <?php if (hasPermission('hosting', 'export csv')): ?>
            <a href="?export=csv" class="btn btn-success">Export CSV</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="viewModalLabel">Hosting Record Details</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="viewDetails" class="row g-3"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Hosting Records Table -->
    <div class="card p-4 shadow-sm">
      <h4 class="text-primary mb-4">Hosting Records</h4>
      <div class="table-responsive">
        <table class="table table-bordered table-hover text-center align-middle">
          <thead class="table-light">
            <tr>
              <?php foreach ($columns as $col): ?>
                <th><?= ucwords(str_replace('_', ' ', $col)) ?></th>
              <?php endforeach; ?>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $limit = 30; // Only show 30 records initially
            $count = 0;
            while ($r = $data->fetch_assoc()):
              if ($count >= $limit)
                break;
              ?>
              <tr>
                <?php foreach ($columns as $col): ?>
                  <td>
                    <?= $col === 'password' ? '******' : htmlspecialchars($r[$col]) ?>
                  </td>
                <?php endforeach; ?>
                <td>
                  <div class="btn-group" role="group" aria-label="Actions">
                    <button type="button" class="btn btn-sm text-secondary" title="View" onclick="openViewModal(
    '<?= htmlspecialchars($r['client_name']) ?>',
    '<?= htmlspecialchars($r['client_id']) ?>',
    '<?= htmlspecialchars($r['location']) ?>',
    '<?= htmlspecialchars($r['asset_type']) ?>',
    '<?= htmlspecialchars($r['host']) ?>',
    '<?= htmlspecialchars($r['server_name']) ?>',
    '<?= htmlspecialchars($r['os']) ?>',
    '<?= htmlspecialchars($r['cpu'] ?? '') ?>',
    '<?= htmlspecialchars($r['ram'] ?? '') ?>',
    '<?= htmlspecialchars($r['sata'] ?? '') ?>',
    '<?= htmlspecialchars($r['ssd'] ?? '') ?>',
    '<?= htmlspecialchars($r['private_ip'] ?? '') ?>',
    '<?= htmlspecialchars($r['public_ip'] ?? '') ?>',
    '<?= htmlspecialchars($r['username']) ?>',
    '<?= htmlspecialchars($r['password']) ?>',
    '<?= htmlspecialchars($r['spla']) ?>',
    '<?= htmlspecialchars($r['login_url']) ?>',
    '<?= htmlspecialchars($r['note']) ?>'
  )"><i class="fas fa-eye"></i></button>
  <?php if(hasPermission('hosting','update')): ?>
                    <button type="button" class="btn btn-sm text-info" title="Edit" onclick="openEditModal(
  '<?= $r['id'] ?>',
  '<?= htmlspecialchars($r['client_name']) ?>',
  '<?= htmlspecialchars($r['client_id']) ?>',
  '<?= htmlspecialchars($r['location']) ?>',
  '<?= htmlspecialchars($r['asset_type']) ?>',
  '<?= htmlspecialchars($r['host']) ?>',
  '<?= htmlspecialchars($r['server_name']) ?>',
  '<?= htmlspecialchars($r['os']) ?>',
  '<?= !empty($r['cpu']) ? htmlspecialchars($r['cpu']) : '' ?>',
  '<?= !empty($r['ram']) ? htmlspecialchars($r['ram']) : '' ?>',
  '<?= !empty($r['sata']) ? htmlspecialchars($r['sata']) : '' ?>',
  '<?= !empty($r['ssd']) ? htmlspecialchars($r['ssd']) : '' ?>',
  '<?= !empty($r['private_ip']) ? htmlspecialchars($r['private_ip']) : '' ?>',
  '<?= !empty($r['public_ip']) ? htmlspecialchars($r['public_ip']) : '' ?>',
  '<?= htmlspecialchars($r['username']) ?>',
  '<?= htmlspecialchars($r['password']) ?>',
  '<?= htmlspecialchars($r['spla']) ?>',
  '<?= htmlspecialchars($r['login_url']) ?>',
  '<?= htmlspecialchars($r['note']) ?>'
)">
                      <i class="fas fa-edit"></i>
                    </button>
                    <?php endif;?>
                    <?php if (hasPermission('hosting', 'delete')): ?>
                      <form method="POST" class="d-inline"
                        onsubmit="return confirm('Are you sure you want to delete this record?');">
                        <input type="hidden" name="hosting_record_id" value="<?= $r['id'] ?>">
                        <button type="submit" name="delete_hosting_record" class="btn btn-sm text-danger" title="Delete">
                          <i class="fas fa-trash-alt"></i>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>

                </td>
              </tr>
              <?php
              $count++;
            endwhile; ?>
          </tbody>
        </table>

        <!-- View More Button -->
        <!-- <?php if ($data->num_rows > $limit): ?>
          <div class="text-center mt-3">
            <a href="full_hosting_list.php" class="btn btn-primary">
              View All Records
            </a>
          </div>
        <?php endif; ?> -->
      </div>
    </div>

    <!-- Edit Hosting Modal -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script>
      function openViewModal(client_name, client_id, location, asset_type, host, server_name, os, cpu, ram, sata, ssd, private_ip, public_ip, username, password, spla, login_url, note) {
        const html = `
    <div class="col-md-6"><strong>Client Name:</strong> ${client_name}</div>
    <div class="col-md-6"><strong>Client ID:</strong> ${client_id}</div>
    <div class="col-md-6"><strong>Location:</strong> ${location}</div>
    <div class="col-md-6"><strong>Asset Type:</strong> ${asset_type}</div>
    <div class="col-md-6"><strong>Host:</strong> ${host}</div>
    <div class="col-md-6"><strong>Server Name:</strong> ${server_name}</div>
    <div class="col-md-6"><strong>OS:</strong> ${os}</div>
    <div class="col-md-6"><strong>CPU:</strong> ${cpu}</div>
    <div class="col-md-6"><strong>RAM:</strong> ${ram}</div>
    <div class="col-md-6"><strong>SATA:</strong> ${sata}</div>
    <div class="col-md-6"><strong>SSD:</strong> ${ssd}</div>
    <div class="col-md-6"><strong>Private IP:</strong> ${private_ip}</div>
    <div class="col-md-6"><strong>Public IP:</strong> ${public_ip}</div>
    <div class="col-md-6"><strong>Username:</strong> ${username}</div>
    <div class="col-md-6"><strong>Password:</strong> ${password}</div>
    <div class="col-md-6"><strong>SPLA:</strong> ${spla}</div>
    <div class="col-md-12"><strong>Login URL:</strong> ${login_url}</div>
    <div class="col-md-12"><strong>Note:</strong> ${note}</div>
  `;
        document.getElementById('viewDetails').innerHTML = html;
        new bootstrap.Modal(document.getElementById('viewModal')).show();
      }

      function openEditModal(
        id,
        client_name,
        client_id,
        location,
        asset_type,
        host,
        server_name,
        os,
        cpu,
        ram,
        sata,
        ssd,
        private_ip,
        public_ip,
        username,
        password,
        spla,
        login_url,
        note
      ) {
        document.getElementById('form_mode_id').value = id;
        document.getElementById('client_name').value = client_name;
        document.getElementById('client_id').value = client_id;
        document.getElementById('location').value = location;
        document.getElementById('asset_type').value = asset_type;
        toggleFieldsBasedOnAssetType(asset_type);

        document.getElementById('host').value = host;
        document.getElementById('server_name').value = server_name;
        document.getElementById('os').value = os;
        document.getElementById('cpu').value = cpu;
        document.getElementById('ram').value = ram;
        document.getElementById('sata').value = sata;
        document.getElementById('ssd').value = ssd;
        document.getElementById('private_ip').value = private_ip;
        document.getElementById('public_ip').value = public_ip;
        document.getElementById('username').value = username;
        document.getElementById('password').value = password;
        document.getElementById('spla').value = spla;
        document.getElementById('login_url').value = login_url;
        document.getElementById('note').value = note;

        document.getElementById('form_submit_btn').innerText = 'Update Hosting Record';

        toggleFieldsBasedOnOS(document.getElementById('asset_type'));

        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
        setTimeout(() => document.getElementById('client_name').focus(), 400);
      }

      document.getElementById('Editform').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        const id = document.getElementById('form_mode_id').value;
        formData.append('action', id ? 'edit' : 'add');

        // Log the form data
        for (let pair of formData.entries()) {
          console.log(pair[0] + ': ' + pair[1]);
        }

        axios.post('backend.php', formData)
          .then(response => {
            const res = response.data.trim();
            let msg = '';
            if (res === 'success_add') msg = 'Hosting Record Added ✅';
            else if (res === 'success_update') msg = 'Hosting Record Updated ✅';
            else msg = '❌ Error: ' + res;

            showAlert(msg, res.startsWith('success') ? 'success' : 'danger');
            if (res.startsWith('success')) setTimeout(() => location.reload(), 1500);
          })
          .catch(error => {
            showAlert('Server Error ❌', 'danger');
            console.error(error);
          });
      });

      function deleteRecord(id) {
        if (!confirm("Delete this record?")) return;
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        axios.post('backend.php', formData)
          .then(response => {
            const res = response.data.trim();
            showAlert(res === 'success_delete' ? 'Record Deleted ✅' : res, res === 'success_delete' ? 'success' : 'danger');
            if (res === 'success_delete') setTimeout(() => location.reload(), 1000);
          })
          .catch(() => showAlert('Server Error ❌', 'danger'));
      }


      function updateClientId(selectElement) {
        // Get the selected option
        const selectedOption = selectElement.options[selectElement.selectedIndex];

        // Get the client ID from the data attribute
        const clientId = selectedOption.getAttribute('data-client-id');

        // Set the Client ID field value and disable it
        document.getElementById('client_id').value = clientId || '';
      }
    </script>

    <script>
      function toggleFieldsBasedOnOS(selectElement) {
        const selectedOS = selectElement.value;
        const vmFields = document.querySelectorAll('.vm-field');
        const hostField = document.getElementById('host').parentElement; // Host field container
        const serverNameField = document.getElementById('server_name').parentElement; // Server Name field container

        if (selectedOS === 'VM' || selectedOS == "Physical Server") {
          // Show VM-specific fields
          vmFields.forEach(field => {
            field.style.display = 'block';
            field.querySelector('input, select').required = true;
          });

          // Show Host and Server Name fields
          hostField.style.display = 'block';
          serverNameField.style.display = 'block';
        } else {
          // Hide VM-specific fields
          vmFields.forEach(field => {
            field.style.display = 'none';
            field.querySelector('input, select').required = false;
          });

          // Hide Host and Server Name fields
          hostField.style.display = 'none';
          serverNameField.style.display = 'none';
        }
      }

      function showAlert(message, type = 'success') {
        const alertBox = document.getElementById('alertBox');
        alertBox.className = `alert alert-${type}`;
        alertBox.innerText = message;
        alertBox.classList.remove('d-none');
        setTimeout(() => alertBox.classList.add('d-none'), 4000);
      }
      function toggleFieldsBasedOnAssetType(asset_type) {
        const vmFields = document.querySelectorAll('.vm-field');
        const show = asset_type === 'VM' || asset_type === 'Physical Server';

        vmFields.forEach(field => {
          field.style.display = show ? 'block' : 'none';
          const input = field.querySelector('input, select');
          if (input) input.required = show;
        });
      }

    </script>

</body>

</html>