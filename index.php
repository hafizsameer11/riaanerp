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
        <?php
        $helpdeskNavOk = (isset($_SESSION['role']) && strtolower((string) $_SESSION['role']) === 'admin')
          || (isset($_SESSION['permissions']['helpdesk']) && count($_SESSION['permissions']['helpdesk']) > 0);
        $hdAllTicketsNav = (isset($_SESSION['role']) && strtolower((string) $_SESSION['role']) === 'admin')
          || (!empty($_SESSION['permissions']['helpdesk']) && in_array('edit ticket', $_SESSION['permissions']['helpdesk'], true));
        $showServersTab = hasPermission('Hosting and Licensing', 'hosting') || hasPermission('Hosting and Licensing', 'spla');
        $showSautechTab = hasPermission('Hosting and Licensing', 'logins') || hasPermission('Hosting and Licensing', 'devices');
        $showClientsTab = hasPermission('clients') || hasPermission('billing', 'wip') || hasPermission('billing', 'quotes');
        $showBillingTab = hasPermission('billing', 'billing') || hasPermission('billing', 'expenses');
        $clientsFirstSrc = hasPermission('clients')
          ? 'modules/clientinfo/clientinfo.php'
          : (hasPermission('billing', 'quotes') ? 'modules/billing/quotes.php' : 'modules/billing/wip.php');
        $sautechFirstSrc = hasPermission('Hosting and Licensing', 'devices')
          ? 'modules/device/index.php'
          : 'modules/hostandlic/login/register.php';
        $serversFirstSrc = hasPermission('Hosting and Licensing', 'hosting')
          ? 'modules/hostandlic/hosting.php'
          : 'modules/spla/index.php';
        $billingFirstSrc = hasPermission('billing', 'billing')
          ? 'modules/billing/billing.php'
          : 'modules/billing/expenses.php';
        $reportingFirstSrc = hasPermission('report and admin', 'billing report')
          ? 'modules/reports/billingReport.php'
          : 'modules/reports/reseller_commission.php';
        $adminFirstSrc = 'modules/services/Calculator.php';
        if (hasPermission('admin service', 'Finance Calculator')) {
            $adminFirstSrc = 'modules/services/Calculator.php';
        } elseif (hasPermission('admin service', 'Manage Hosting Assets')) {
            $adminFirstSrc = 'modules/services/manage_hosting_assets.php';
        } elseif (hasPermission('admin service', 'Manage Invoice Companies')) {
            $adminFirstSrc = 'modules/services/supplier/invoice-company/index.php';
        } elseif (hasPermission('admin service', 'Manage Service Categories')) {
            $adminFirstSrc = 'modules/services/supplier/service-category/billing-service-category.php';
        } elseif (hasPermission('admin service', 'Manage Service Types')) {
            $adminFirstSrc = 'modules/services/supplier/service-type/service-type.php';
        } elseif (hasPermission('admin service', 'Manage Suppliers')) {
            $adminFirstSrc = 'modules/services/supplier/supplier/supplier.php';
        } elseif (hasPermission('admin service', 'Reseller')) {
            $adminFirstSrc = 'modules/services/supplier/reseller/reseller.php';
        } elseif (hasPermission('report and admin', 'role management')) {
            $adminFirstSrc = 'modules/reports/roles/roleManagement.php';
        } elseif (hasPermission('admin service', 'Unit Prices')) {
            $adminFirstSrc = 'modules/services/supplier/unit-price/index.php';
        } elseif (hasPermission('report and admin', 'user logins')) {
            $adminFirstSrc = 'modules/auth/register.php';
        }
        ?>

        <!-- Admin (alphabetical subtabs) -->
        <?php if (hasPermission('admin service') || hasPermission('report and admin', 'user logins') || hasPermission('report and admin', 'role management')): ?>
          <a href="#" class="tabbtn" data-tab-id="admin" data-src="<?= htmlspecialchars($adminFirstSrc, ENT_QUOTES, 'UTF-8') ?>">Admin</a>
          <div class="sub-tabs" style="display: none; padding-left: 20px;">
            <?php if (hasPermission('admin service', 'Finance Calculator')): ?>
              <a href="#" data-src="modules/services/Calculator.php" class="w-100 p-3 sub-tabbtn">Finance Calculator</a>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Manage Hosting Assets')): ?>
              <a href="#" data-src="modules/services/manage_hosting_assets.php" class="w-100 p-3 sub-tabbtn">Manage Hosting Assets</a>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Manage Invoice Companies')): ?>
              <a href="#" data-src="modules/services/supplier/invoice-company/index.php" class="w-100 p-3 sub-tabbtn">Manage Invoice Companies</a>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Manage Service Categories')): ?>
              <a href="#" data-src="modules/services/supplier/service-category/billing-service-category.php" class="w-100 p-3 sub-tabbtn">Manage Service Categories</a>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Manage Service Types')): ?>
              <a href="#" data-src="modules/services/supplier/service-type/service-type.php" class="w-100 p-3 sub-tabbtn">Manage Service Types</a>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Manage Suppliers')): ?>
              <a href="#" data-src="modules/services/supplier/supplier/supplier.php" class="w-100 p-3 sub-tabbtn">Manage Suppliers</a>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Reseller')): ?>
              <a href="#" data-src="modules/services/supplier/reseller/reseller.php" class="w-100 p-3 sub-tabbtn">Reseller</a>
            <?php endif; ?>
            <?php if (hasPermission('report and admin', 'role management')): ?>
              <a href="#" data-src="modules/reports/roles/roleManagement.php" class="w-100 p-3 sub-tabbtn">Role Management</a>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Unit Prices')): ?>
              <a href="#" data-src="modules/services/supplier/unit-price/index.php" class="w-100 p-3 sub-tabbtn">Unit Prices</a>
            <?php endif; ?>
            <?php if (hasPermission('report and admin', 'user logins')): ?>
              <a href="#" data-src="modules/auth/register.php" class="w-100 p-3 sub-tabbtn">User Logins</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Backup Monitoring (alphabetical subtabs) -->
        <?php if (hasPermission('admin service')): ?>
          <a href="#" class="tabbtn" data-tab-id="backup" data-src="modules/backup_monitoring/index.php">Backup Monitoring</a>
          <div class="sub-tabs" style="display:none;padding-left:20px">
            <?php if (hasPermission('billing', 'backup_monitoring')): ?>
              <a href="#" data-src="modules/backup_monitoring/index.php?type=CLIENT" class="sub-tabbtn w-100">Client</a>
            <?php endif; ?>
            <?php if (hasPermission('billing', 'backup_monitoring')): ?>
              <a href="#" data-src="modules/backup_monitoring/index.php?type=NAS" class="sub-tabbtn w-100">NAS</a>
            <?php endif; ?>
            <?php if (hasPermission('billing', 'backup_monitoring')): ?>
              <a href="#" data-src="modules/backup_monitoring/index.php?type=UNDEFINED" class="sub-tabbtn w-100">Undefined Mails Backup</a>
            <?php endif; ?>
            <?php if (hasPermission('billing', 'backup_monitoring')): ?>
              <a href="#" data-src="modules/backup_monitoring/index.php?type=VEEAM" class="sub-tabbtn w-100">Veeam</a>
            <?php endif; ?>
            <?php if (hasPermission('billing', 'backup_monitoring')): ?>
              <a href="#" data-src="modules/backup_monitoring/index.php?type=VEEAMCLOUD" class="sub-tabbtn w-100">VeeamCloud</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Billing: Billing & Expenses only -->
        <?php if ($showBillingTab): ?>
          <a href="#" class="tabbtn" data-tab-id="billing" data-src="<?= htmlspecialchars($billingFirstSrc, ENT_QUOTES, 'UTF-8') ?>">Billing</a>
          <div class="sub-tabs" style="display:none;padding-left:20px">
            <?php if (hasPermission('billing', 'billing')): ?>
              <a href="#" data-src="modules/billing/billing.php" class="sub-tabbtn w-100">Billing</a>
            <?php endif; ?>
            <?php if (hasPermission('billing', 'expenses')): ?>
              <a href="#" data-src="modules/billing/expenses.php" class="sub-tabbtn w-100">Expenses</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Clients + WIP + Quotes (alphabetical subtabs) -->
        <?php if ($showClientsTab): ?>
          <a href="#" class="tabbtn" data-tab-id="clients" data-src="<?= htmlspecialchars($clientsFirstSrc, ENT_QUOTES, 'UTF-8') ?>">Clients</a>
          <div class="sub-tabs" style="display:none;padding-left:20px">
            <?php if (hasPermission('clients')): ?>
              <a href="#" data-src="modules/clientinfo/clientinfo.php" class="sub-tabbtn w-100">Clients</a>
            <?php endif; ?>
            <?php if (hasPermission('billing', 'quotes')): ?>
              <a href="#" data-src="modules/billing/quotes.php" class="sub-tabbtn w-100">Quotes</a>
            <?php endif; ?>
            <?php if (hasPermission('billing', 'wip')): ?>
              <a href="#" data-src="modules/billing/wip.php" class="sub-tabbtn w-100">WIP</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Dashboard (landing) -->
        <a href="#" class="tabbtn" data-tab-id="dashboard" data-src="dashboard.php">Dashboard</a>

        <!-- Firewall Monitor -->
        <?php if (hasPermission('admin service')): ?>
          <a href="#" class="tabbtn" data-tab-id="firewall" data-src="modules/firewall/index.php">Firewall Monitor</a>
        <?php endif; ?>

        <!-- Helpdesk (alphabetical subtabs) -->
        <?php if ($helpdeskNavOk): ?>
          <a href="#" class="tabbtn" data-tab-id="helpdesk" data-src="modules/helpdesk/index.php">Helpdesk</a>
          <div class="sub-tabs" style="display:none;padding-left:20px">
            <?php if ($hdAllTicketsNav): ?>
              <a href="#" data-src="modules/helpdesk/tickets_all.php" class="sub-tabbtn w-100">All tickets</a>
            <?php endif; ?>
            <?php if (hasPermission('helpdesk', 'dashboard')): ?>
              <a href="#" data-src="modules/helpdesk/index.php" class="sub-tabbtn w-100">Dashboard</a>
            <?php endif; ?>
            <?php if (hasPermission('helpdesk', 'admin mail')): ?>
              <a href="#" data-src="modules/helpdesk/admin/templates.php" class="sub-tabbtn w-100">Email Templates</a>
            <?php endif; ?>
            <?php if (hasPermission('helpdesk', 'admin mail')): ?>
              <a href="#" data-src="modules/helpdesk/admin/mail_settings.php" class="sub-tabbtn w-100">Mail Settings</a>
            <?php endif; ?>
            <?php if (hasPermission('helpdesk', 'merge tickets')): ?>
              <a href="#" data-src="modules/helpdesk/merge.php" class="sub-tabbtn w-100">Merge Tickets</a>
            <?php endif; ?>
            <?php if (hasPermission('helpdesk', 'create ticket')): ?>
              <a href="#" data-src="modules/helpdesk/ticket_new.php" class="sub-tabbtn w-100">New Request</a>
            <?php endif; ?>
            <?php if (hasPermission('helpdesk', 'admin mail')): ?>
              <a href="#" data-src="modules/helpdesk/admin/rules.php" class="sub-tabbtn w-100">Notification Rules</a>
            <?php endif; ?>
            <?php if (hasPermission('helpdesk', 'reports')): ?>
              <a href="#" data-src="modules/helpdesk/reports/index.php" class="sub-tabbtn w-100">Reports</a>
            <?php endif; ?>
            <?php if (hasPermission('helpdesk', 'requesters')): ?>
              <a href="#" data-src="modules/helpdesk/requesters.php" class="sub-tabbtn w-100">Requesters</a>
            <?php endif; ?>
            <?php if (hasPermission('helpdesk', 'scheduled')): ?>
              <a href="#" data-src="modules/helpdesk/scheduled.php" class="sub-tabbtn w-100">Scheduled Calls</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Reporting: Billing Report + Reseller commission only -->
        <?php if (hasPermission('report and admin', 'billing report') || hasPermission('report and admin', 'reseller commission')): ?>
          <a href="#" class="tabbtn" data-tab-id="reporting" data-src="<?= htmlspecialchars($reportingFirstSrc, ENT_QUOTES, 'UTF-8') ?>">Reporting</a>
          <div class="sub-tabs" style="display:none;padding-left:20px">
            <?php if (hasPermission('report and admin', 'billing report')): ?>
              <a href="#" data-src="modules/reports/billingReport.php" class="sub-tabbtn w-100">Billing Report</a>
            <?php endif; ?>
            <?php if (hasPermission('report and admin', 'reseller commission')): ?>
              <a href="#" data-src="modules/reports/reseller_commission.php" class="sub-tabbtn w-100">Reseller Commission Report</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Sautech: Logins + Devices -->
        <?php if ($showSautechTab): ?>
          <a href="#" class="tabbtn" data-tab-id="sautech" data-src="<?= htmlspecialchars($sautechFirstSrc, ENT_QUOTES, 'UTF-8') ?>">Sautech</a>
          <div class="sub-tabs" style="display: none; padding-left: 20px;">
            <?php if (hasPermission('Hosting and Licensing', 'devices')): ?>
              <a href="#" data-src="modules/device/index.php" class="w-100 sub-tabbtn">Devices</a>
            <?php endif; ?>
            <?php if (hasPermission('Hosting and Licensing', 'logins')): ?>
              <a href="#" data-src="modules/hostandlic/login/register.php" class="w-100 sub-tabbtn">Logins</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Servers & Licensing: Servers + SPLA -->
        <?php if ($showServersTab): ?>
          <a href="#" class="tabbtn" data-tab-id="servers" data-src="<?= htmlspecialchars($serversFirstSrc, ENT_QUOTES, 'UTF-8') ?>">Servers &amp; Licensing</a>
          <div class="sub-tabs" style="display: none; padding-left: 20px;">
            <?php if (hasPermission('Hosting and Licensing', 'hosting')): ?>
              <a href="#" data-src="modules/hostandlic/hosting.php" class="w-100 sub-tabbtn">Servers</a>
            <?php endif; ?>
            <?php if (hasPermission('Hosting and Licensing', 'spla')): ?>
              <a href="#" data-src="modules/spla/index.php" class="w-100 sub-tabbtn">SPLA Licensing</a>
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
        src="dashboard.php"></iframe>
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

      // Landing page (same permission model; sidebar opens modules as before)
      frame.src = 'dashboard.php';
    });

  </script>
</body>

</html>