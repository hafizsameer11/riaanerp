<!DOCTYPE html>
<html>

<head>
    <title>Expenses Module</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body class="p-5">
    <div class="p-4">
        <div class="d-flex align-items-center ">
            <?php session_start(); ?>
            <h1>Role Management</h1>
        </div>
        <div class="row gap-2 p-2">
            <a href="roles.php" class="btn btn-primary col-md-3">Roles</a>
            <a href="permissions.php" class="btn btn-success col-md-3">Role Permissions</a>
        </div>
    </div>
</body>

</html>