<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<?php

session_start();
include('./modules/components/permissioncheck.php');
if (!isset($_SESSION['user_id'])) {
  header("Location: modules/auth/login.php");
  exit();
}
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
  session_destroy();
  header("Location: modules/auth/login.php");
  exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Sautech ERP System</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap">
  <link rel="stylesheet" href="assets/css/index.css">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      margin: 0;
      background: #f4f7fa;
      color: #222;
      /* background-color:#1e4d86; */
    }

    header {
      background-color: #1e2a38;
      padding: 20px;
      color: white;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    header img {
      height: 40px;
    }

    .tabs {
      display: flex;
      background: #fff;
      border-bottom: 2px solid #ddd;
    }

    .tabs a {
      flex: 1;
      padding: 15px;
      text-align: center;
      text-decoration: none;
      background: #f0f0f0;
      color: #333;
      font-weight: 600;
      transition: background 0.3s, color 0.3s;
      border-right: 1px solid #ddd;
    }

    .tabs a:last-child {
      border-right: none;
    }

    .tabs a.active,
    .tabs a:hover {
      background: #1e2a38;
      color: white;
    }

    .content {
      padding: 0;
    }

    iframe {
      width: 100%;
      height: 85vh;
      border: none;
      background: #fff;
    }

    .header-title {
      font-size: 18px;
      font-weight: 500;
      color: #ffffff;
      font-family: 'Inter', sans-serif;
      margin: 0;
      letter-spacing: 0.5px;
      text-transform: capitalize;
      border-left: 2px solid #1abc9c;
      padding-left: 12px;
    }
  </style>
</head>

<body>
  <header>
    <div class="container-1">
      <div>
        <img src="assets/img/logofinal.png" class="logo">
      </div>
    </div>
    <div class="header-title">
      <a href="?logout=1"
        style="all:unset;padding:10px 20px;background-color:white;font-weight:800;color:black;border-radius:5px;cursor: pointer;">Logout</a>
    </div>
  </header>


  <div class="tabs">
    <?php if (hasPermission('Hosting and Licensing')): ?>
      <a href="" class="tabbtn active" data-src="modules/hostandlic/index.php">Hosting and Licensing</a>
    <?php endif; ?>
    <?php if (hasPermission('admin service')): ?>
      <a href="" class="tabbtn" data-src="modules/services/finance.php">Admin Services</a>
    <?php endif; ?>
    <?php if (hasPermission('clients')): ?>
      <a href="" class="tabbtn" data-src="modules/clientinfo/clientinfo.php">Clients</a>
    <?php endif; ?>
    <?php if (hasPermission('billing')): ?>
      <a href="" class="tabbtn" data-src="modules/billing/billing_list.php">Billing</a>
    <?php endif; ?>
    <?php if (hasPermission('report and admin')): ?>
      <a href="" class="tabbtn" data-src="modules/reports/index.php">Reporting and Admin</a>
    <?php endif; ?>
  </div>

  <div class="content">
    <iframe id="moduleFrame" src="modules/hostandlic/index.php"></iframe>
  </div>
  <script>
    // Redirect to the first available tabbtn
    window.addEventListener('DOMContentLoaded', () => {
      const firstTab = document.querySelector('.tabbtn');
      if (firstTab) {
        firstTab.classList.add('active');
        document.getElementById('moduleFrame').src = firstTab.dataset.src;
      }
    });
  </script>
  <script>
    // reidrect the first tabtbn data-src

    document.querySelectorAll(".tabbtn").forEach(link => {
      link.addEventListener("click", function (e) {
        e.preventDefault();
        const frame = document.getElementById("moduleFrame");
        frame.src = this.dataset.src;

        document.querySelectorAll(".tabbtn").forEach(l => l.classList.remove("active"));
        this.classList.add("active");
      });
    });
  </script>
</body>

</html>