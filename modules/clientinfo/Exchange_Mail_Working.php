<?php
if (isset($_POST['export_csv']) && isset($_POST['client_id'])) {
  $client_id = intval($_POST['client_id']);
include_once '../../config.php'; // Ensure this path is correct
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="mailboxes.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Domain', 'Email', 'Full Name', 'Password', 'SpamTitan', 'Note']);
  $csv = $conn->query("SELECT domain, email, full_name, password, spamtitan, note FROM exchange_mailboxes WHERE client_id = $client_id");
  while ($r = $csv->fetch_assoc())
    fputcsv($out, $r);
  fclose($out);
  exit;
}
?>







<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct

if ($conn->connect_error)
  die("Connection failed: " . $conn->connect_error);

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
if ($client_id <= 0)
  die("Invalid Client ID");

$client_name = $conn->query("SELECT client_name FROM clients WHERE id = $client_id")->fetch_assoc()['client_name'] ?? 'Unknown';

// --- Domain Management ---
if (isset($_POST['add_domain']) && !empty($_POST['new_domain'])) {
  $domain = $conn->real_escape_string($_POST['new_domain']);
  $conn->query("INSERT INTO exchange_domains (client_id, domain) VALUES ($client_id, '$domain')");
}
if (isset($_POST['delete_domain']) && !empty($_POST['delete_domain_val'])) {
  $domain = $conn->real_escape_string($_POST['delete_domain_val']);
  $conn->query("DELETE FROM exchange_domains WHERE client_id = $client_id AND domain = '$domain'");
}

// --- SpamTitan Management ---
if (isset($_POST['add_spamtitan']) && !empty($_POST['new_spamtitan'])) {
  $spam = $conn->real_escape_string($_POST['new_spamtitan']);
  $conn->query("INSERT INTO spamtitan_servers (client_id, hostname) VALUES ($client_id, '$spam')");
}
if (isset($_POST['delete_spamtitan']) && !empty($_POST['delete_spamtitan_val'])) {
  $spam = $conn->real_escape_string($_POST['delete_spamtitan_val']);
  $conn->query("DELETE FROM spamtitan_servers WHERE client_id = $client_id AND hostname = '$spam'");
}

// --- Delete Mailbox ---

if (isset($_POST['delete_mailbox']) && isset($_POST['mailbox_id'])) {
  $mailbox_id = intval($_POST['mailbox_id']);
  $conn->query("DELETE FROM exchange_mailboxes WHERE id=$mailbox_id AND client_id=$client_id");
}


// --- Add Mailbox ---

if (isset($_POST['add_mailbox']) && isset($_POST['email']) && isset($_POST['password']) && isset($_POST['full_name']) && isset($_POST['selected_domain']) && isset($_POST['selected_spamtitan'])) {
  $email = $conn->real_escape_string($_POST['email']);
  $password = $conn->real_escape_string($_POST['password']);
  $full_name = $conn->real_escape_string($_POST['full_name']);
  $note = isset($_POST['note']) ? $conn->real_escape_string($_POST['note']) : '';
  $domain = $conn->real_escape_string($_POST['selected_domain']);
  $spamtitan = $conn->real_escape_string($_POST['selected_spamtitan']);
  $conn->query("INSERT INTO exchange_mailboxes (client_id, domain, email, password, full_name, spamtitan, note)
                  VALUES ($client_id, '$domain', '$email', '$password', '$full_name', '$spamtitan', '$note')");
}


// --- Edit Mailbox ---
if (isset($_POST['edit_mailbox']) && isset($_POST['mailbox_id'])) {
  $mailbox_id = intval($_POST['mailbox_id']);
  $email = $conn->real_escape_string($_POST['email']);
  $password = $conn->real_escape_string($_POST['password']);
  $full_name = $conn->real_escape_string($_POST['full_name']);
  $note = $conn->real_escape_string($_POST['note']);
  $domain = $conn->real_escape_string($_POST['selected_domain']);
  $spamtitan = $conn->real_escape_string($_POST['selected_spamtitan']);
  $conn->query("UPDATE exchange_mailboxes SET domain='$domain', email='$email', password='$password',
                  full_name='$full_name', spamtitan='$spamtitan', note='$note'
                  WHERE id=$mailbox_id AND client_id=$client_id");
}

// --- Export to CSV ---
if (isset($_POST['export_csv'])) {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="mailboxes.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Domain', 'Email', 'Full Name', 'Password', 'SpamTitan', 'Note']);
  $csv = $conn->query("SELECT domain, email, full_name, password, spamtitan, note FROM exchange_mailboxes WHERE client_id = $client_id");
  while ($r = $csv->fetch_assoc())
    fputcsv($out, $r);
  fclose($out);
  exit;
}

$domains = $conn->query("SELECT domain FROM exchange_domains WHERE client_id = $client_id");
$spamtitans = $conn->query("SELECT hostname FROM spamtitan_servers WHERE client_id = $client_id");
$mailboxes = $conn->query("SELECT * FROM exchange_mailboxes WHERE client_id = $client_id" . (isset($_GET['filter_domain']) && $_GET['filter_domain'] != '' ? " AND domain = '" . $conn->real_escape_string($_GET['filter_domain']) . "'" : "") . "");
?>

<!DOCTYPE html>
<html>

<head>
  <title>Hosted Exchange for <?= htmlspecialchars($client_name) ?></title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      margin: 30px;
      background: #f4f4f9;
    }

    h2,
    h3 {
      color: #333;
    }

    form,
    table {
      background: white;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    input,
    select,
    button {
      padding: 6px;
      margin: 4px 0;
      border: 1px solid #ccc;
      border-radius: 4px;
    }

    button {
      background: #007bff;
      color: white;
      border: none;
      cursor: pointer;
    }

    button:hover {
      background: #0056b3;
    }

    .scroll-box {
      max-height: 300px;
      overflow-y: auto;
      border: 1px solid #ddd;
      border-radius: 6px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      border: 1px solid #ccc;
      padding: 6px;
      text-align: left;
    }

    th {
      background-color: #e9ecef;
    }

    .form-group {
      margin-bottom: 10px;
    }
  </style>
  <script>
    function editMailbox(id, domain, email, password, full_name, spamtitan, note) {
      document.getElementById('mailbox_id').value = id;
      document.getElementById('selected_domain').value = domain;
      document.getElementById('email').value = email;
      document.getElementById('password').value = password;
      document.getElementById('full_name').value = full_name;
      document.getElementById('selected_spamtitan').value = spamtitan;
      document.getElementById('note').value = note;
      document.getElementById('edit_button').style.display = 'inline-block';
      document.getElementById('add_button').style.display = 'none';
    }

    function searchMailbox() {
      let input = document.getElementById('search').value.toLowerCase();
      let rows = document.querySelectorAll('#mailbox_table tbody tr');
      rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? '' : 'none';
      });
    }
  </script>
</head>

<body>




    <h2>Hosted Exchange Mailboxes - <?= htmlspecialchars($client_name) ?></h2>


  <!-- Domain Management -->
  <form method="post" style="margin-bottom:20px; background:#fff; padding:10px; border-radius:6px;">
    <label>Domain:</label>
    <select name="selected_domain">
      <?php $dlist = $conn->query("SELECT domain FROM exchange_domains WHERE client_id = $client_id");
      while ($d = $dlist->fetch_assoc()): ?>
        <option value="<?= $d['domain'] ?>"><?= $d['domain'] ?></option>
      <?php endwhile; ?>
    </select>
    <input type="text" name="new_domain" placeholder="Add Domain">
    <button type="submit" name="add_domain">Add</button>
    <input type="text" name="delete_domain_val" placeholder="Delete Domain">
    <button type="submit" name="delete_domain">Delete</button>
  </form>


  <!-- SpamTitan Management -->
  <form method="post" style="margin-bottom:20px; background:#fff; padding:10px; border-radius:6px;">
    <label>SpamTitan:</label>
    <select name="selected_spamtitan">
      <?php $slist = $conn->query("SELECT hostname FROM spamtitan_servers WHERE client_id = $client_id");
      while ($s = $slist->fetch_assoc()): ?>
        <option value="<?= $s['hostname'] ?>"><?= $s['hostname'] ?></option>
      <?php endwhile; ?>
    </select>
    <input type="text" name="new_spamtitan" placeholder="Add Server">
    <button type="submit" name="add_spamtitan">Add</button>
    <input type="text" name="delete_spamtitan_val" placeholder="Delete Server">
    <button type="submit" name="delete_spamtitan">Delete</button>
  </form>

  <form method="post">
    <div class="form-group">
      <label>Domain:</label>
      <select name="selected_domain" id="selected_domain" required>
        <?php $d = $conn->query("SELECT domain FROM exchange_domains WHERE client_id = $client_id");
        while ($r = $d->fetch_assoc()): ?>
          <option value="<?= $r['domain'] ?>"><?= $r['domain'] ?></option>
        <?php endwhile; ?>
      </select>

      <label>Full Name:</label>
      <input type="text" name="full_name" id="full_name" required>

      <label>Email:</label>
      <input type="email" name="email" id="email" required>

      <label>Password:</label>
      <input type="text" name="password" id="password" required>

      <label>SpamTitan:</label>
      <select name="selected_spamtitan" id="selected_spamtitan" required>
        <?php $s = $conn->query("SELECT hostname FROM spamtitan_servers WHERE client_id = $client_id");
        while ($r = $s->fetch_assoc()): ?>
          <option value="<?= $r['hostname'] ?>"><?= $r['hostname'] ?></option>
        <?php endwhile; ?>
      </select>

      <label>Note:</label>
      <input type="text" name="note" id="note">
    </div>

    <input type="hidden" name="mailbox_id" id="mailbox_id">
    <button type="submit" name="add_mailbox" id="add_button">Add Mailbox</button>
    <button type="submit" name="edit_mailbox" id="edit_button" style="display:none;">Update Mailbox</button>
  </form>

  </form>

  </div>
  </form>








  <form method="post" style="float:right; margin-top:-30px; margin-bottom:10px;">
    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">

  </form>

  <h3>Mailboxes</h3>

  <form method="post" style="margin-bottom: 10px;">
    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
    <button type="submit" name="export_csv"
      style="background:green; color:white; padding:6px 12px; border:none; border-radius:4px;">
      Export CSV
    </button>
  </form>

  <input type="text" id="search" onkeyup="searchMailbox()" placeholder="Search mailboxes...">
  <form method="get" style="margin-top:10px;">
    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
    <label for="filter_domain">Filter by Domain:</label>
    <select name="filter_domain" onchange="this.form.submit();">
      <option value="">-- All Domains --</option>
      <?php
      $domain_list = $conn->query("SELECT DISTINCT domain FROM exchange_mailboxes WHERE client_id = $client_id");
      while ($dom = $domain_list->fetch_assoc()):
        $selected = (isset($_GET['filter_domain']) && $_GET['filter_domain'] == $dom['domain']) ? 'selected' : '';
        echo "<option value='{$dom['domain']}' $selected>{$dom['domain']}</option>";
      endwhile;
      ?>
    </select>
  </form>

  <div class="scroll-box">
    <table id="mailbox_table">
      <thead>
        <tr>
          <th>Domain</th>
          <th>Email</th>
          <th>Full Name</th>
          <th>Password</th>
          <th>SpamTitan</th>
          <th>Note</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        while ($m = $mailboxes->fetch_assoc()): ?>
          <tr>
            <td><?= $m['domain'] ?></td>
            <td><?= $m['email'] ?></td>
            <td><?= $m['full_name'] ?></td>
            <td><?= $m['password'] ?></td>
            <td><?= $m['spamtitan'] ?></td>
            <td><?= $m['note'] ?></td>

            <td>
              <form method="post" style="display:flex; gap:5px; align-items:center;">
                <input type="hidden" name="mailbox_id" value="<?php echo $m['id']; ?>">
                <button type="button"
                  onclick="editMailbox('<?php echo $m['id']; ?>', '<?php echo $m['domain']; ?>', '<?php echo $m['email']; ?>', '<?php echo $m['password']; ?>', '<?php echo $m['full_name']; ?>', '<?php echo $m['spamtitan']; ?>', '<?php echo $m['note']; ?>')"
                  style="background:#007bff;color:white;border:none;padding:5px 10px;border-radius:4px;">Edit</button>
                <button type="submit" name="delete_mailbox"
                  style="background:#dc3545;color:white;border:none;padding:5px 10px;border-radius:4px;">Delete</button>
              </form>
            </td>

          </tr>
          <?php
          error_reporting(E_ALL);
          ini_set('display_errors', 1);
        endwhile; ?>
      </tbody>
    </table>
  </div>

</body>

</html>