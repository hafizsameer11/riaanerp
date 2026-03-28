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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
  <link rel="stylesheet" href="assets/css/index.css">
  <style>
    .specific-scroll::-webkit-scrollbar {
      width: 8px;
    }

    /* Scrollbar Thumb */
    .specific-scroll::-webkit-scrollbar-thumb {
      background-color: #1e2a38;
      border-radius: 10px;
      transition: background-color 0.3s ease;
    }

    /* Scrollbar Thumb Hover */
    .specific-scroll::-webkit-scrollbar-thumb:hover {
      background-color: #1e2a38;
    }

    /* Optional: Scrollbar Track Background */
    .specific-scroll::-webkit-scrollbar-track {
      background-color: #1e2a38;
      border-radius: 10px;
    }

    /* Firefox (fallback for full compatibility) */
    .specific-scroll {
      scrollbar-width: thin;
      scrollbar-color: white #1e2a38;
    }

    body {
      font-family: 'Inter', sans-serif;
      margin: 0;
      padding: 0;
      background: #f4f7fa;
      color: #222;
      /* background-color:#1e4d86; */
    }

    .sidebar {
      background-color: #1e2a38;
      height: 100vh;
      overflow: auto;
    }

    header {
      background-color: #1e2a38;
      padding: 20px;
      color: white;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0;
    }

    header img {
      height: 40px;
    }

    .tabs {
      display: flex;
      flex-direction: column;
      padding-left: 10px;
      padding-top: 0px;
      margin-bottom: 20px;
      /* gap: 10px; */
      /* background: #fff; */
      /* border-bottom: 2px solid #ddd; */
    }

    .tabs a {
      padding: 15px;
      text-align: left;
      font-size: 14px;
      text-decoration: none;
      /* background: #f0f0f0; */
      color: rgb(211, 210, 210);
      font-weight: 600;
      transition: background 0.3s, color 0.3s;
    }

    .tabs a:last-child {
      border-right: none;
    }

    .tabs a.active,
    .tabs a:hover {
      background: #3f5772;
      color: white;
      border-top-left-radius: 10px;
      border-bottom-left-radius: 10px;
    }

    .tabs a.active {
      margin-bottom: 0;
      border-bottom-left-radius: 0;
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

    .logo {
      width: 150px;
      height: auto;
    }

    .container-1 {
      width: 100%;
      padding-left: 20px;
    }

    .logout-btn {
      all: unset;
      width: 70%;
      margin: 0;
      margin-inline: auto;
      margin-block: 20px;
      display: block;
      padding: 10px 20px;
      background-color: white;
      font-weight: 800;
      color: black;
      border-radius: 5px;
      cursor: pointer;
    }

    .sub-tabs {
      background: #3f5772;
      margin-bottom: 20px;
      border-bottom-left-radius: 20px;
    }

    .sub-tabs a {
      font-size: 12px;
      padding: 8px 14px;
      text-decoration: none;
      display: block;
      font-weight: 500;
    }

    .sub-tabs a.active,
    .sub-tabs a:hover {
      color: white;
    }

    .sub-tabs a:last-child {
      padding-bottom: 20px;
    }
  </style>
</head>

<body>
  <div class="row p-0 m-0">
    <div class="col-md-2 p-0 m-0 sidebar specific-scroll">
      <header>
        <div class="container-1">
          <img src="assets/img/logofinal.png" class="logo">
        </div>
      </header>
      <div class="tabs">
        <!-- hostings -->
        <?php if (hasPermission('Hosting and Licensing')): ?>
          <a href="#" class="tabbtn" data-src="modules/hostandlic/index.php">Hosting and Licensing</a>
          <div class="sub-tabs" style="display: none; padding-left: 20px;">
            <?php if (hasPermission('Hosting and Licensing', 'hosting')): ?>
              <a href="#" data-src="modules/hostandlic/hosting.php" class="w-100 sub-tabbtn">Hosting</a>
            <?php endif; ?>
            <?php if (hasPermission('Hosting and Licensing', 'logins')): ?>
              <a href="#" data-src="modules/hostandlic/login/register.php" class=" w-100 sub-tabbtn">Logins</a>
            <?php endif; ?>
            <?php if (hasPermission('Hosting and Licensing', 'spla')): ?>
              <a href="#" data-src="modules/spla/index.php" class="w-100 sub-tabbtn">SPLA Licensing</a>
            <?php endif; ?>
            <?php if (hasPermission('Hosting and Licensing', 'devices')): ?>
              <a href="#" data-src="modules/device/index.php" class="w-100 sub-tabbtn">Devices</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- admin service -->
        <?php if (hasPermission('admin service')): ?>
          <a href="" class="tabbtn" data-src="modules/services/finance.php">Admin Services</a>
          <div class="sub-tabs" style="display: none; padding-left: 20px;">
            <?php if (hasPermission('admin service', 'Manage Suppliers')): ?>
              <a href="#" data-src="modules/services/supplier/supplier/supplier.php" class=" w-100 p-3 sub-tabbtn">Manage
                Suppliers</a>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Manage Service Types')): ?>
              <a href="#" data-src="modules/services/supplier/service-type/service-type.php"
                class="w-100 p-3 sub-tabbtn">Manage Service
                Types</a>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Manage Service Categories')): ?>
              <a href="#" data-src="modules/services/supplier/service-category/billing-service-category.php"
                class="w-100 p-3 sub-tabbtn">Manage
                Service Categories</a>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Unit Prices')): ?>
              <a href="#" data-src="modules/services/supplier/unit-price/index.php" class="w-100 p-3 sub-tabbtn">Unit
                Prices</a>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Manage Hosting Assets')): ?>
              <a href="#" data-src="modules/services/manage_hosting_assets.php" class="w-100 p-3 sub-tabbtn">Manage Hosting
                Assets</a>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Manage Invoice Companies')): ?>
              <a href="#" data-src="modules/services/supplier/invoice-company/index.php" class="w-100 p-3 sub-tabbtn">Manage
                Invoice
                Companies</a>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Finance Calculator')): ?>
              <a href="#" data-src="modules/services/Calculator.php" class="w-100 p-3 sub-tabbtn">Finance Calculator</a>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Reseller')): ?>
              <a href="#" data-src="modules/services/supplier/reseller/reseller.php"
                class=" w-100 p-3 sub-tabbtn">Reseller</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>


        <!-- clients -->
        <?php if (hasPermission('clients')): ?>
          <a href="" class="tabbtn" data-src="modules/clientinfo/clientinfo.php">Clients</a>

        <?php endif; ?>

        <!-- firewall-monitor -->
        <?php if (hasPermission('admin service')): ?>
          <a href="#" class="tabbtn" data-src="modules/firewall/index.php">Firewall Monitor</a>

        <?php endif; ?>

        <!-- billings -->
        <?php if (hasPermission('billing')): ?>
          <a href="" class="tabbtn" data-src="modules/billing/billing_list.php">Billing</a>
          <div class="sub-tabs" style="display:none;padding-left:20px">
            <?php if (hasPermission('billing', 'billing')): ?>
              <a href="#" data-src="modules/billing/billing.php" class="sub-tabbtn  w-100">Billing</a>
            <?php endif; ?>
            <?php if (hasPermission('billing', 'wip')): ?>
              <a href="#" data-src="modules/billing/wip.php" class="sub-tabbtn  w-100">WIP</a>
            <?php endif; ?>
            <?php if (hasPermission('billing', 'quotes')): ?>
              <a href="#" data-src="modules/billing/quotes.php" class="w-100 sub-tabbtn">Quotes</a>
            <?php endif; ?>
            <?php if (hasPermission('billing', 'expenses')): ?>
              <a href="#" data-src="modules/billing/expenses.php" class="sub-tabbtn  w-100">Expenses</a>
            <?php endif; ?>
            <!-- <?php if (hasPermission('billing', 'backup_monitoring')): ?>
              <a href="#" data-src="modules/billing/backup_monitoring/index.php" class="sub-tabbtn  w-100">Backup Monitoring</a>
            <?php endif; ?> -->
          </div>
        <?php endif; ?>

        <!-- Backup Monitoring -->
        <?php if (hasPermission('admin service')): ?>
          <a href="" class="tabbtn" data-src="modules/backup_monitoring/index.php">Backup Monitoring</a>
          <div class="sub-tabs" style="display:none;padding-left:20px">
            <?php if (hasPermission('billing', 'backup_monitoring')): ?>
              <a href="#" data-src="modules/backup_monitoring/index.php?type=CLIENT" class="sub-tabbtn w-100">Client</a>
            <?php endif; ?>
            <?php if (hasPermission('billing', 'backup_monitoring')): ?>
              <a href="#" data-src="modules/backup_monitoring/index.php?type=VEEAM" class="sub-tabbtn w-100">Veeam</a>
            <?php endif; ?>
            <?php if (hasPermission('billing', 'backup_monitoring')): ?>
              <a href="#" data-src="modules/backup_monitoring/index.php?type=VEEAMCLOUD" class="sub-tabbtn w-100">VeeamCloud</a>
            <?php endif; ?>
            <?php if (hasPermission('billing', 'backup_monitoring')): ?>
              <a href="#" data-src="modules/backup_monitoring/index.php?type=NAS" class="sub-tabbtn w-100">NAS</a>
            <?php endif; ?>
            <?php if (hasPermission('billing', 'backup_monitoring')): ?>
              <a href="#" data-src="modules/backup_monitoring/index.php?type=UNDEFINED" class="sub-tabbtn w-100">Undefined Mails Backup</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Helpdesk -->
        <?php
        $helpdeskNavOk = (isset($_SESSION['role']) && strtolower((string) $_SESSION['role']) === 'admin')
          || (isset($_SESSION['permissions']['helpdesk']) && count($_SESSION['permissions']['helpdesk']) > 0);
        ?>
        <?php if ($helpdeskNavOk): ?>
          <a href="" class="tabbtn" data-src="modules/helpdesk/index.php">Helpdesk</a>
          <div class="sub-tabs" style="display:none;padding-left:20px">
            <?php if (hasPermission('helpdesk', 'dashboard')): ?>
              <a href="#" data-src="modules/helpdesk/index.php" class="sub-tabbtn w-100">Dashboard</a>
            <?php endif; ?>
            <?php if (hasPermission('helpdesk', 'create ticket')): ?>
              <a href="#" data-src="modules/helpdesk/ticket_new.php" class="sub-tabbtn w-100">New Request</a>
            <?php endif; ?>
            <?php if (hasPermission('helpdesk', 'scheduled')): ?>
              <a href="#" data-src="modules/helpdesk/scheduled.php" class="sub-tabbtn w-100">Scheduled Calls</a>
            <?php endif; ?>
            <?php if (hasPermission('helpdesk', 'requesters')): ?>
              <a href="#" data-src="modules/helpdesk/requesters.php" class="sub-tabbtn w-100">Requesters</a>
            <?php endif; ?>
            <?php if (hasPermission('helpdesk', 'merge tickets')): ?>
              <a href="#" data-src="modules/helpdesk/merge.php" class="sub-tabbtn w-100">Merge Tickets</a>
            <?php endif; ?>
            <?php if (hasPermission('helpdesk', 'admin mail')): ?>
              <a href="#" data-src="modules/helpdesk/admin/mail_settings.php" class="sub-tabbtn w-100">Mail Settings</a>
            <?php endif; ?>
            <?php if (hasPermission('helpdesk', 'admin mail')): ?>
              <a href="#" data-src="modules/helpdesk/admin/templates.php" class="sub-tabbtn w-100">Email Templates</a>
            <?php endif; ?>
            <?php if (hasPermission('helpdesk', 'admin mail')): ?>
              <a href="#" data-src="modules/helpdesk/admin/rules.php" class="sub-tabbtn w-100">Notification Rules</a>
            <?php endif; ?>
            <?php if (hasPermission('helpdesk', 'reports')): ?>
              <a href="#" data-src="modules/helpdesk/reports/index.php" class="sub-tabbtn w-100">Reports</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- report -->
        <?php if (hasPermission('report and admin')): ?>
          <a href="" class="tabbtn" data-src="modules/reports/index.php">Reporting and Admin</a>
          <div class="sub-tabs" style="display:none;padding-left:20px">
            <?php if (hasPermission('report and admin', 'user logins')): ?>
              <a href="#" data-src="modules/auth/register.php" class="b sub-tabbtn ">User Logins</a>
            <?php endif; ?>
            <?php if (hasPermission('report and admin', 'billing report')): ?>
              <a href="#" data-src="modules/reports/billingReport.php" class=" sub-tabbtn  ">Billing Report</a>
            <?php endif; ?>
            <?php if (hasPermission('report and admin', 'role management')): ?>
              <a href="#" data-src="modules/reports/roles/roleManagement.php" class="b sub-tabbtn  ">Role Management</a>
            <?php endif; ?>
            <?php if (hasPermission('report and admin', 'reseller commission')): ?>
              <a href="#" data-src="modules/reports/reseller_commission.php" class="b sub-tabbtn">Reseller Commission Report</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <div>
        <a href="?logout=1" class="logout-btn">Logout</a>
      </div>
    </div>
    <div class="content col-md-10 p-0 m-0">
      <iframe id="moduleFrame" style="height:99vh;overflow:auto;margin:0;padding:0;"
        src="modules/hostandlic/index.php"></iframe>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO"
    crossorigin="anonymous"></script>
  <script>
    // Redirect to the first available tabbtn
    document.addEventListener('DOMContentLoaded', () => {
      const frame = document.getElementById("moduleFrame");
      const tabButtons = document.querySelectorAll('.tabbtn');
      const subTabButtons = document.querySelectorAll('.sub-tabbtn');

      // Handle main tabs
      tabButtons.forEach(tab => {
        tab.addEventListener('click', function (e) {
          e.preventDefault();

          // Remove active from all main and sub tabs
          tabButtons.forEach(btn => btn.classList.remove('active'));
          subTabButtons.forEach(btn => btn.classList.remove('active'));
          document.querySelectorAll('.sub-tabs').forEach(sub => sub.style.display = 'none');

          // Activate this main tab
          this.classList.add('active');

          // Show its sub-tabs if exist
          const nextElement = this.nextElementSibling;
          if (nextElement && nextElement.classList.contains('sub-tabs')) {
            nextElement.style.display = 'block';

            // Load first sub-tab if available
            const firstSubTab = nextElement.querySelector('.sub-tabbtn');
            if (firstSubTab) {
              firstSubTab.classList.add('active');
              frame.src = firstSubTab.dataset.src;
              return;
            }
          }

          // Otherwise load main tab content
          frame.src = this.dataset.src;
        });
      });

      // Handle sub-tabs
      subTabButtons.forEach(sub => {
        sub.addEventListener('click', function (e) {
          e.preventDefault();
          subTabButtons.forEach(btn => btn.classList.remove('active'));
          this.classList.add('active');
          frame.src = this.dataset.src;
        });
      });

      // Trigger default tab
      if (tabButtons.length) {
        tabButtons[0].click();
      }
    });

  </script>
</body>

</html>