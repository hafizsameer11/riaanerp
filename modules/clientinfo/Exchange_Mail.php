<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../../config.php'; // Ensure this path is correct
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
if ($client_id <= 0)
    die("Invalid Client ID");

// Add Domain
if (isset($_POST['add_domain']) && !empty($_POST['new_domain'])) {
    $domain = $conn->real_escape_string($_POST['new_domain']);
    $conn->query("INSERT INTO exchange_domains (client_id, domain) VALUES ($client_id, '$domain')");
}

// Remove Domain
if (isset($_POST['remove_domain']) && !empty($_POST['selected_domain'])) {
    $domain = $conn->real_escape_string($_POST['selected_domain']);
    $conn->query("DELETE FROM exchange_domains WHERE client_id = $client_id AND domain = '$domain'");
}

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM exchange_mailboxes WHERE id = $delete_id AND client_id = $client_id");
    header("Location: ?client_id=$client_id");
    exit;
}
$editing = false;
$edit_mailbox = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $res = $conn->query("SELECT * FROM exchange_mailboxes WHERE id = $edit_id AND client_id = $client_id");
    if ($res && $res->num_rows > 0) {
        $edit_mailbox = $res->fetch_assoc();
        $editing = true;
    }
}
if (isset($_POST['update_mailbox']) && isset($_POST['edit_id'])) {
    $edit_id = intval($_POST['edit_id']);
    $domain = $conn->real_escape_string($_POST['selected_domain']);
    $spamtitan = $conn->real_escape_string($_POST['selected_spamtitan']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $note = $conn->real_escape_string($_POST['note']);

    $conn->query("UPDATE exchange_mailboxes SET domain='$domain', spamtitan='$spamtitan', email='$email', password='$password', full_name='$full_name', note='$note' WHERE id=$edit_id AND client_id = $client_id");

    header("Location: ?client_id=$client_id");
    exit;
}


// Add SpamTitan
if (isset($_POST['add_spamtitan']) && !empty($_POST['new_spamtitan'])) {
    $spamtitan = $conn->real_escape_string($_POST['new_spamtitan']);
    $conn->query("INSERT INTO spamtitan_servers (client_id, hostname) VALUES ($client_id, '$spamtitan')");
}

// Remove SpamTitan
if (isset($_POST['remove_spamtitan']) && !empty($_POST['selected_spamtitan'])) {
    $spamtitan = $conn->real_escape_string($_POST['selected_spamtitan']);
    $conn->query("DELETE FROM spamtitan_servers WHERE client_id = $client_id AND hostname = '$spamtitan'");
}

// Save mailbox entry
// if (isset($_POST['add_mailbox'])) {
//     // Save new domain if provided
//     if (!empty($_POST['new_domain'])) {
//         $newDomain = $conn->real_escape_string($_POST['new_domain']);
//         $conn->query("INSERT INTO exchange_domains (client_id, domain) VALUES ($client_id, '$newDomain')");
//         $domain = $newDomain;
//     } else {
//         $domain = $conn->real_escape_string($_POST['selected_domain']);
//     }

//     // Save new SpamTitan if provided
//     if (!empty($_POST['new_spamtitan'])) {
//         $newSpam = $conn->real_escape_string($_POST['new_spamtitan']);
//         $conn->query("INSERT INTO spamtitan_servers (client_id, hostname) VALUES ($client_id, '$newSpam')");
//         $spamtitan = $newSpam;
//     } else {
//         $spamtitan = $conn->real_escape_string($_POST['selected_spamtitan']);
//     }

//     $email = $conn->real_escape_string($_POST['email']);
//     $password = $conn->real_escape_string($_POST['password']);
//     $full_name = $conn->real_escape_string($_POST['full_name']);
//     $note = $conn->real_escape_string($_POST['note'] ?? '');

//     if (!empty($domain) && !empty($spamtitan)) {
//         $query = "INSERT INTO exchange_mailboxes (client_id, domain, email, password, full_name, spamtitan, note)
//                   VALUES ($client_id, '$domain', '$email', '$password', '$full_name', '$spamtitan', '$note')";
//         if (!$conn->query($query)) {
//             echo "❌ Error saving mailbox: " . $conn->error;
//         }
//     } else {
//         echo "⚠️ Please select or enter both a domain and a SpamTitan server.";
//     }
// }

// Add Mailbox
if (isset($_POST['add_mailbox'])) {
    $domain = $conn->real_escape_string($_POST['selected_domain']);
    $spamtitan = $conn->real_escape_string($_POST['selected_spamtitan']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $note = $conn->real_escape_string($_POST['note'] ?? '');

    if (!empty($domain) && !empty($spamtitan)) {
        $query = "INSERT INTO exchange_mailboxes (client_id, domain, email, password, full_name, spamtitan, note)
                  VALUES ($client_id, '$domain', '$email', '$password', '$full_name', '$spamtitan', '$note')";
        if (!$conn->query($query)) {
            echo "❌ Error saving mailbox: " . $conn->error;
        }
    } else {
        echo "⚠️ Please select or enter both a domain and a SpamTitan server.";
    }
}

$domains = $conn->query("SELECT domain FROM exchange_domains WHERE client_id = $client_id");
$spams = $conn->query("SELECT hostname FROM spamtitan_servers WHERE client_id = $client_id");
$mailboxes = $conn->query("SELECT * FROM exchange_mailboxes WHERE client_id = $client_id");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Exchange Mailboxes</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body style="background: #f9f9f9; padding: 20px;">

    <div class=" my-4" style="width: 95%; margin: auto; ">

        <div class="d-flex align-items-center mb-4">
            <?php include('../components/Backbtn.php') ?>
            <h3 class="text-dark">Exchange Mailboxes for Client ID: <?= htmlspecialchars($client_id) ?></h>
        </div>

        <form method="post" class="bg-white p-4 rounded shadow-sm mb-5 mt-3">

            <!-- Domains Section -->
            <h5 class="mb-3">Domains</h5>
            <div class="row g-3 align-items-center mb-3">
                <div class="col-md-6">
                    <select name="selected_domain" class="form-select">
                        <option value="">Select a Domain</option>
                        <?php mysqli_data_seek($domains, 0);
                        while ($d = $domains->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($d['domain']) ?>"><?= htmlspecialchars($d['domain']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" name="new_domain" class="form-control" placeholder="Add Domain">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" name="add_domain" class="btn btn-success btn-sm w-100">Add</button>
                    <button type="submit" name="remove_domain" class="btn btn-danger btn-sm w-100">Remove</button>
                </div>
            </div>

            <!-- SpamTitan Section -->
            <h5 class="mb-3">SpamTitan Servers</h5>
            <div class="row g-3 align-items-center mb-3">
                <div class="col-md-6">
                    <select name="selected_spamtitan" class="form-select">
                        <option value="">Select a SpamTitan Server</option>
                        <?php mysqli_data_seek($spams, 0);
                        while ($s = $spams->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($s['hostname']) ?>"><?= htmlspecialchars($s['hostname']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" name="new_spamtitan" class="form-control" placeholder="Add Server">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" name="add_spamtitan" class="btn btn-success btn-sm w-100">Add</button>
                    <button type="submit" name="remove_spamtitan" class="btn btn-danger btn-sm w-100">Remove</button>
                </div>
            </div>

            <!-- Mailbox Section -->
            <h5 class="mb-3">Add Mailbox</h5>
            <div class="row g-3">
                <div class="col-md-3">
                    <input type="email" name="email" class="form-control" placeholder="Email Address" >
                </div>
                <div class="col-md-3">
                    <input type="text" name="password" class="form-control" placeholder="Password" >
                </div>
                <div class="col-md-3">
                    <input type="text" name="full_name" class="form-control" placeholder="Full Name" >
                </div>
                <div class="col-md-3">
                    <input type="text" name="note" class="form-control" placeholder="Note">
                </div>
            </div>
            <div class="mt-3 text-end">
                <button type="submit" name="add_mailbox" class="btn btn-primary">
                    Add Mailbox
                </button>
            </div>

        </form>


        <h4 class="mb-3 text-dark">Saved Mailboxes</h4>

        <table class="table table-hover table-bordered text-center align-middle">
            <thead class="table-light">
                <tr>
                    <th>Domain</th>
                    <th>Email</th>
                    <th>Name</th>
                    <th>Password</th>
                    <th>SpamTitan</th>
                    <th>Note</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($m = $mailboxes->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['domain']) ?></td>
                        <td><?= htmlspecialchars($m['email']) ?></td>
                        <td><?= htmlspecialchars($m['full_name']) ?></td>
                        <td><?= htmlspecialchars($m['password']) ?></td>
                        <td><?= htmlspecialchars($m['spamtitan']) ?></td>
                        <td><?= htmlspecialchars($m['note']) ?></td>
                        <td>
                            <a href="?client_id=<?= $client_id ?>&edit_id=<?= $m['id'] ?>"
                                class="btn btn-sm btn-warning">Edit</a>
                            <a href="?client_id=<?= $client_id ?>&delete_id=<?= $m['id'] ?>" class="btn btn-sm btn-danger"
                                onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>