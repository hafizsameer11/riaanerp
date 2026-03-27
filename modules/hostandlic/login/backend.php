<?php
$db_host = "localhost";
    $db_user = "clientzone_user";
    $db_pass = "S@utech2024!";
    $db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
if(isset($_POST['register_user'])){
    $id = $_POST['client_id'];
    $device_type = $_POST['device_type'];
    $device_ip = $_POST['device_ip'];
    $location = $_POST['location'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $note = $_POST['note'];
    $url = $_POST['url'];
   $sql =  "INSERT INTO `hosting_logins` ( `client_id`, `device_type`, `device_ip`, `location`, `username`, `password`, `url`, `note`) 
    VALUES ('$id', '$device_type', '$device_ip', '$location', '$username', '$password', '$url', '$note')";
    $result = mysqli_query($conn,$sql);
    if($result){
        header('location:register.php');
    }else{
        echo "Error";
    }
}
//update
if (isset($_POST['update_user'])) {
    $id = intval($_POST['update_user_id']);
    $client_id = $_POST['client_id'];
    $device_type = $_POST['device_type'];
    $device_ip = $_POST['device_ip'];
    $location = $_POST['location'];
    $url = $_POST['url'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $note = $_POST['note'];

    $update_sql = "UPDATE `hosting_logins` SET 
        client_id = '$client_id',
        device_type = '$device_type',
        device_ip = '$device_ip',
        location = '$location',
        url = '$url',
        username = '$username',
        password = '$password',
        note = '$note'
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
if(isset($_GET['delete_user'])){
    $id = $_GET['delete_user'];
    $sql = "Delete From `hosting_logins` WHERE `id` = '$id';";
    $result = mysqli_query($conn,$sql);
    if($result){
        header('location:register.php');
    }else{
        echo "Error";
    }
}


?>


