<?php
//login

include_once '../../config.php'; // Ensure this path is correct
session_start();
// When login form submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get username and password
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    $query = "SELECT * FROM `registers` WHERE `username` = '$username' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        if ($password === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $role = $user['role_id'];
            // fetch role
            $query = "SELECT * FROM `roles` WHERE `id` = '$role' LIMIT 1";
            $result = mysqli_query($conn, $query);
            $role = mysqli_fetch_assoc($result);
            $_SESSION['role'] = $role['name'];


            // Fetch user from users table to get role_id
            $userId = $user['id'];
            $userRow = $conn->query("SELECT * FROM registers WHERE id = $userId")->fetch_assoc();
            if ($userRow) {
                $roleId = $userRow['role_id'];

                // Fetch permissions for this role
                $permissions = [];
                $permResult = $conn->query("SELECT page, function_name FROM permissions WHERE role_id = $roleId AND allowed = 1");
                while ($perm = $permResult->fetch_assoc()) {
                    $permissions[$perm['page']][] = $perm['function_name'];
                }
                $_SESSION['permissions'] = $permissions;
            } else {
                $_SESSION['permissions'] = [];
                        echo "<script>alert('No permissions found!'); window.history.back();</script>";
            }

            header("Location: ../../index.php");
            exit();
        } else {
            echo "<script>alert('Incorrect password!'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('User not found!'); window.history.back();</script>";
    }
}
?>