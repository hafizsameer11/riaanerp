<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Management</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

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

        /* Modal Custom */
        #userModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
            z-index: 9999;
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            max-width: 900px;
            position: relative;
        }

        .btn-close {
            position: absolute;
            right: 20px;
            top: 20px;
        }
    </style>
</head>

<body>
    <!-- Add New User Button -->
    <div class="d-flex justify-content-between align-items-center mt-5 mb-4" style="width: 95%; margin: auto;">
        <h3 class="mb-0 d-flex align-items-center">
            <i class="bi bi-people-fill me-2 text-secondary" style="font-size: 1.5rem;"></i>
            <div class="d-flex align-items-center">
                <?php session_start(); ?>
                <?php include('../../components/permissioncheck.php') ?>
                <span class="fw-semibold text-dark">All Records</span>
            </div>
        </h3>
        <!-- Trigger Button -->
        <?php if (hasPermission('logins', 'create')): ?>
            <button id="showUserForm" class="btn btn-primary mb-4">
                <i class="fas fa-user-plus"></i> Add
            </button>
        <?php endif; ?>
    </div>

    <!-- Search Form -->
    <div style="width: 95%; margin: auto;" class="mb-4">
        <form method="GET" action="register.php" class="card shadow-sm p-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-9">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by Username, Device Type, IP, Location, or URL..." 
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($_GET['search'])): ?>
                        <a href="register.php" class="btn btn-secondary w-100 mt-2">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
    <?php
    $db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

    include_once '../../config.php'; // Ensure this path is correct
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $viewing = false;
    $view_data = [];

    if (isset($_GET['view'])) {
        $id = intval($_GET['view']); // Secure way
    
        $query = "SELECT hosting_logins.*, clients.client_name 
                  FROM hosting_logins 
                  LEFT JOIN clients ON hosting_logins.client_id = clients.id 
                  WHERE hosting_logins.id = $id";

        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $view_data = mysqli_fetch_assoc($result);
            $viewing = true;
        }
    }

    $editing = false;

    if (isset($_GET['edit'])) {
        $id = intval($_GET['edit']);

        $query = "SELECT * FROM `hosting_logins` WHERE id = $id";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $view_data = mysqli_fetch_assoc($result);
            $editing = true;
        }
    }
    // Clients
    $clients = [];
    $result = $conn->query("SELECT id, client_name FROM clients");
    while ($row = $result->fetch_assoc()) {
        $clients[] = $row;
    }

    ?>

    <?php if ($viewing && $view_data): ?>
        <div class="my-4" style="width: 95%; margin: auto;">
            <div class="card border-0 rounded-3 bg-light">
                <div class="card-header bg-light text-black d-flex align-items-center">
                    <i class="bi bi-eye-fill me-2" style="font-size: 1.5rem;"></i>
                    <h4 class="mb-0">Viewing User: <?= htmlspecialchars($view_data['client_name']) ?></h4>
                </div>

                <div class="card-body">
                    <div class="row g-6">
                        <div class="col-md-6">
                            <div class="d-flex flex-column gap-2">
                                <p class="mb-0"><strong>Client ID:</strong> <?= htmlspecialchars($view_data['client_id']) ?>
                                </p>
                                <p class="mb-0"><strong>Device Type:</strong>
                                    <?= htmlspecialchars($view_data['device_type']) ?></p>
                                <p class="mb-0"><strong>Device IP:</strong> <?= htmlspecialchars($view_data['device_ip']) ?>
                                </p>
                                <p class="mb-0"><strong>Note:</strong> <?= htmlspecialchars($view_data['note']) ?></p>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="d-flex flex-column gap-2">
                                <p class="mb-0"><strong>Username:</strong> <?= htmlspecialchars($view_data['username']) ?>
                                </p>
                                <p class="mb-0"><strong>Password:</strong> <?= htmlspecialchars($view_data['password']) ?>
                                </p>
                                <p class="mb-0"><strong>URL:</strong> <a href="<?= htmlspecialchars($view_data['url']) ?>"
                                        target="_blank"><?= htmlspecialchars($view_data['url']) ?></a></p>
                                <p class="mb-0"><strong>Device Location:</strong>
                                    <?= htmlspecialchars($view_data['location']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light border-0">
                    <a href="register.php<?= !empty($_GET['search']) ? '?search=' . urlencode($_GET['search']) : '' ?>" class="btn btn-secondary">Back to Users</a>
                </div>
            </div>
        </div>
    <?php endif; ?>



    <?php if ($editing): ?>
        <form method="post" action="backend.php" class="container bg-light p-4 rounded shadow-sm my-4">
            <h3 class="mb-4 text-warning">Edit User</h3>
            <div class="row g-3">

                <div class="col-md-12">
                    <label class="form-label">Client Name</label>
                    <select name="client_id" class="form-control" required>
                        <option value="">Select Client</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= htmlspecialchars($client['id']) ?>" <?= isset($view_data['client_id']) && $view_data['client_id'] == $client['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['client_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Device Type</label>
                    <input type="text" name="device_type" class="form-control"
                        value="<?= htmlspecialchars($view_data['device_type']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Device IP</label>
                    <input type="text" name="device_ip" class="form-control"
                        value="<?= htmlspecialchars($view_data['device_ip']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control"
                        value="<?= htmlspecialchars($view_data['location']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">URL</label>
                    <input type="url" name="url" class="form-control" value="<?= htmlspecialchars($view_data['url']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control"
                        value="<?= htmlspecialchars($view_data['username']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password</label>
                    <input type="text" name="password" class="form-control"
                        value="<?= htmlspecialchars($view_data['password']) ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Note</label>
                    <textarea name="note" class="form-control" rows="4" placeholder="Enter note here..."></textarea>
                </div>

            </div>

            <input type="hidden" name="update_user_id" value="<?= $view_data['id'] ?>">

            <div class="mt-4 d-flex gap-2">
                <button type="submit" name="update_user" class="btn btn-warning">
                    Update User
                </button>
                <a href="register.php<?= !empty($_GET['search']) ? '?search=' . urlencode($_GET['search']) : '' ?>" class="btn btn-secondary">Back</a>
            </div>
        </form>
    <?php endif; ?>
    <!-- Modal Structure -->
    <div id="userModal">
        <div class="modal-content">

            <!-- Close Button -->
            <button type="button" class="btn-close" aria-label="Close" id="closeUserModal"></button>

            <!-- User Registration Form -->
            <form action="backend.php" method="POST" class="container p-0">
                <h3 class="mb-4 text-success">Enter New Record</h3>

                <div class="row g-3">

                    <div class="col-md-12">
                        <label class="form-label">Client Name</label>
                        <select name="client_id" class="form-control" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= htmlspecialchars($client['id']) ?>">
                                    <?= htmlspecialchars($client['client_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Device Type</label>
                        <input type="text" name="device_type" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Device IP</label>
                        <input type="text" name="device_ip" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">URL</label>
                        <input type="url" name="url" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Note</label>
                        <textarea name="note" class="form-control" rows="4" placeholder="Enter note here..."></textarea>
                    </div>

                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" name="register_user" class="btn btn-success">
                        Save User
                    </button>
                    <button type="button" id="cancelUserForm" class="btn btn-secondary">
                        Cancel
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- Users Table -->
    <div style="width: 95%; margin:auto; margin-top: 30px;">
        <?php if (!empty($_GET['search'])): ?>
            <div class="alert alert-info mb-3">
                <i class="fas fa-info-circle"></i> Search results for: <strong>"<?= htmlspecialchars($_GET['search']) ?>"</strong>
            </div>
        <?php endif; ?>
        <table class="table table-hover table-bordered table-striped align-middle bg-white shadow-sm">
            <thead class="table-light text-center">
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Client Name</th>
                    <th scope="col">Username</th>
                    <th scope="col">Device Type</th>
                    <th scope="col">Device IP</th>
                    <th scope="col">Location</th>
                    <th scope="col">URL</th>
                    <th scope="col">Note</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Example fetching users
                $db_host = "localhost";
                $db_user = "clientzone_user";
                $db_pass = "S@utech2024!";
                $db_name = "clientzone";

                include_once '../../config.php'; // Ensure this path is correct
                if ($conn->connect_error) {
                    die("Connection failed: " . $conn->connect_error);
                }
                
                // Build search query
                $search_query = "SELECT hosting_logins.*, clients.client_name 
                                 FROM hosting_logins 
                                 LEFT JOIN clients ON hosting_logins.client_id = clients.id";
                
                // Add search filter if search term exists
                if (!empty($_GET['search'])) {
                    $search_term = $conn->real_escape_string($_GET['search']);
                    $search_query .= " WHERE (
                        hosting_logins.username LIKE '%$search_term%' OR 
                        hosting_logins.device_type LIKE '%$search_term%' OR 
                        hosting_logins.device_ip LIKE '%$search_term%' OR 
                        hosting_logins.location LIKE '%$search_term%' OR 
                        hosting_logins.url LIKE '%$search_term%'
                    )";
                }
                
                $users = $conn->query($search_query);

                $i = 1;
                while ($user = $users->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?= $i ?></td>
                        <td><?= htmlspecialchars($user['client_name']) ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['device_type']) ?></td>
                        <td><?= htmlspecialchars($user['device_ip']) ?></td>
                        <td><?= htmlspecialchars($user['location']) ?></td>
                        <td><?= htmlspecialchars($user['url']) ?></td>
                        <td><?= htmlspecialchars($user['note']) ?></td>
                        <td class="text-center">
                            <a href="register.php?view=<?= $user['id'] ?><?= !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>" class="btn btn-sm" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if (hasPermission('logins', 'update')): ?>
                                <a href="register.php?edit=<?= $user['id'] ?><?= !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>" class="btn btn-sm" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (hasPermission('logins', 'delete')): ?>
                                <a href="backend.php?delete_user=<?= $user['id'] ?>" class="btn btn-sm text-danger"
                                    onclick="return confirm('Delete this user?')" title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php $i++;
                endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Modal Handling Script -->
    <script>
        const modal = document.getElementById("userModal");
        const openBtn = document.getElementById("showUserForm");
        const closeBtn = document.getElementById("closeUserModal");
        const cancelBtn = document.getElementById("cancelUserForm");

        openBtn.onclick = () => {
            modal.style.display = "block";
        }

        closeBtn.onclick = () => {
            modal.style.display = "none";
        }

        cancelBtn.onclick = () => {
            modal.style.display = "none";
        }

        window.onclick = (e) => {
            if (e.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>

</body>

</html>