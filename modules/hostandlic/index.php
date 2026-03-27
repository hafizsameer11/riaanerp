<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hosting / Logins and Licensing</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f9fbfc;
      color: #333;
      margin: 0;
      padding: 0;
    }

    .logo-text {
      font-size: 24px;
      font-weight: 600;
      color: #2c3e50;
      margin-bottom: 20px;
    }

    .nav-button {
      transition: all 0.3s ease;
    }

    .nav-button:hover {
      transform: translateY(-2px);
    }
  </style>
</head>

<body>
  <?php
  session_start();
  include('../components/permissioncheck.php');
  // include_once '../../assets/sections/header.php'
  ?>
  <div class="p-5">
    <div class="mb-2">
      <div class="logo-text">Manage Hosting, Logins & Licensing</div>
    </div>
    <div class="row g-3">
      <?php if (hasPermission('Hosting and Licensing', 'hosting')): ?>
        <div class="col-12 col-sm-6 col-md-3">
          <a href="hosting.php" class="btn btn-primary w-100 nav-button">Hosting</a>
        </div>
      <?php endif; ?>
      <?php if (hasPermission('Hosting and Licensing', 'logins')): ?>
        <div class="col-12 col-sm-6 col-md-3">
          <a href="login/register.php" class="btn btn-success w-100 nav-button">Logins</a>
        </div>
      <?php endif; ?>
      <?php if (hasPermission('Hosting and Licensing', 'spla')): ?>
        <div class="col-12 col-sm-6 col-md-3">
          <a href="../spla/index.php" class="btn btn-danger w-100 nav-button">SPLA Licensing</a>
        </div>
      <?php endif; ?>
      <?php if (hasPermission('Hosting and Licensing', 'devices')): ?>
        <div class="col-12 col-sm-6 col-md-3">
          <a href="../device/index.php" class="btn btn-secondary w-100 nav-button">Devices</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>