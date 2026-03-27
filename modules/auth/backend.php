<?php
// Live server settings
include_once '../../config.php'; // Ensure this path is correct
if (isset($_POST['register_user'])) {
    $name = $_POST['name'];
    $surname = $_POST['surname'];
    $address = $_POST['address'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role_id = intval($_POST['role_id']);

    $sql = "INSERT INTO `registers` (`name`, `surname`, `address`, `email`, `username`, `password`, `role_id`) 
    VALUES ('$name', '$surname', '$address', '$email', '$username', '$password', $role_id)";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        header('location:register.php');
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
//update
if (isset($_POST['update_user'])) {
    $id = intval($_POST['update_user_id']);
    $name = $_POST['name'];
    $surname = $_POST['surname'];
    $address = $_POST['address'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role_id = intval($_POST['role_id']);

    $update_sql = "UPDATE `registers` SET 
        name = '$name',
        surname = '$surname',
        address = '$address',
        email = '$email',
        username = '$username',
        password = '$password',
        role_id = $role_id
        WHERE id = $id";

    $update_result = mysqli_query($conn, $update_sql);

    if ($update_result) {
        header("Location: register.php?updated=1");
        exit;
    } else {
        echo "Update failed: " . mysqli_error($conn);
    }
}
//Delete
if (isset($_GET['delete_user'])) {
    $id = $_GET['delete_user'];
    $sql = "Delete From `registers` WHERE `id` = '$id';";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        header('location:register.php');
    } else {
        echo "Error";
    }
}


?>