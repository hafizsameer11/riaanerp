<?php
/**
 * Landing page (loaded in iframe from index.php). Same tab visibility rules as sidebar — no new permissions.
 */
session_start();
include __DIR__ . '/modules/components/permissioncheck.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: modules/auth/login.php');
    exit();
}

$helpdeskNavOk = (isset($_SESSION['role']) && strtolower((string) $_SESSION['role']) === 'admin')
    || (isset($_SESSION['permissions']['helpdesk']) && count($_SESSION['permissions']['helpdesk']) > 0);

$showServersTab = hasPermission('Hosting and Licensing', 'hosting') || hasPermission('Hosting and Licensing', 'spla');
$showSautechTab = hasPermission('Hosting and Licensing', 'logins') || hasPermission('Hosting and Licensing', 'devices');
$showClientsTab = hasPermission('clients') || hasPermission('billing', 'wip') || hasPermission('billing', 'quotes');
$showBillingTab = hasPermission('billing', 'billing') || hasPermission('billing', 'expenses');
$showAdminTab = hasPermission('admin service') || hasPermission('report and admin', 'user logins') || hasPermission('report and admin', 'role management');
$showReportingTab = hasPermission('report and admin', 'billing report') || hasPermission('report and admin', 'reseller commission');

$modules = [];
if ($showAdminTab) {
    $modules[] = ['id' => 'admin', 'label' => 'Admin', 'desc' => 'Suppliers, services, finance & access', 'icon' => 'fa-sliders'];
}
if (hasPermission('admin service')) {
    $modules[] = ['id' => 'backup', 'label' => 'Backup Monitoring', 'desc' => 'Client, Veeam, NAS & alerts', 'icon' => 'fa-database'];
}
if ($showBillingTab) {
    $modules[] = ['id' => 'billing', 'label' => 'Billing', 'desc' => 'Billing & expenses', 'icon' => 'fa-file-invoice-dollar'];
}
if ($showClientsTab) {
    $modules[] = ['id' => 'clients', 'label' => 'Clients', 'desc' => 'Clients, quotes & WIP', 'icon' => 'fa-users'];
}
if (hasPermission('admin service')) {
    $modules[] = ['id' => 'firewall', 'label' => 'Firewall Monitor', 'desc' => 'Security monitoring', 'icon' => 'fa-shield-halved'];
}
if ($helpdeskNavOk) {
    $modules[] = ['id' => 'helpdesk', 'label' => 'Helpdesk', 'desc' => 'Tickets & support', 'icon' => 'fa-headset'];
}
if ($showReportingTab) {
    $modules[] = ['id' => 'reporting', 'label' => 'Reporting', 'desc' => 'Billing & reseller reports', 'icon' => 'fa-chart-line'];
}
if ($showSautechTab) {
    $modules[] = ['id' => 'sautech', 'label' => 'Sautech', 'desc' => 'Logins & devices', 'icon' => 'fa-network-wired'];
}
if ($showServersTab) {
    $modules[] = ['id' => 'servers', 'label' => 'Servers & Licensing', 'desc' => 'Servers & SPLA', 'icon' => 'fa-server'];
}

usort($modules, static function ($a, $b) {
    return strcasecmp($a['label'], $b['label']);
});

$displayName = isset($_SESSION['username']) ? htmlspecialchars((string) $_SESSION['username'], ENT_QUOTES, 'UTF-8') : '';
$displayRole = isset($_SESSION['role']) ? htmlspecialchars((string) $_SESSION['role'], ENT_QUOTES, 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard — Sautech ERP</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous">
  <style>
    :root {
      --erp-bg: #f0f4f8;
      --erp-ink: #1e2a38;
      --erp-accent: #3f5772;
      --erp-mint: #1abc9c;
      --card-shadow: 0 4px 24px rgba(30, 42, 56, 0.08);
      --card-hover: 0 12px 40px rgba(30, 42, 56, 0.12);
    }
    * { box-sizing: border-box; }
    body {
      font-family: 'Inter', system-ui, sans-serif;
      margin: 0;
      min-height: 100vh;
      color: var(--erp-ink);
      background: var(--erp-bg);
      background-image:
        radial-gradient(ellipse 120% 80% at 100% 0%, rgba(63, 87, 114, 0.12), transparent 50%),
        radial-gradient(ellipse 100% 60% at 0% 100%, rgba(26, 188, 156, 0.08), transparent 45%);
    }
    .dash-wrap {
      max-width: 1100px;
      margin: 0 auto;
      padding: 2rem 1.5rem 3rem;
    }
    .dash-hero {
      position: relative;
      padding: 2rem 2rem 2.25rem;
      border-radius: 16px;
      background: linear-gradient(135deg, var(--erp-ink) 0%, #2c3e50 55%, var(--erp-accent) 100%);
      color: #fff;
      box-shadow: var(--card-shadow);
      overflow: hidden;
    }
    .dash-hero::after {
      content: '';
      position: absolute;
      top: -40%;
      right: -15%;
      width: 55%;
      height: 180%;
      background: radial-gradient(circle, rgba(26, 188, 156, 0.25) 0%, transparent 65%);
      pointer-events: none;
    }
    .dash-hero-inner { position: relative; z-index: 1; }
    .dash-hero h1 {
      font-size: clamp(1.35rem, 3vw, 1.75rem);
      font-weight: 700;
      letter-spacing: -0.02em;
      margin: 0 0 0.5rem;
    }
    .dash-hero .sub {
      font-size: 0.95rem;
      opacity: 0.88;
      margin: 0;
      max-width: 36rem;
      line-height: 1.5;
    }
    .dash-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      margin-top: 1rem;
      padding: 0.35rem 0.85rem;
      font-size: 0.8rem;
      font-weight: 600;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.12);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .dash-badge i { opacity: 0.9; }
    .dash-section-title {
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: #6b7c8d;
      margin: 2rem 0 1rem;
    }
    .dash-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 1rem;
    }
    .dash-card {
      display: block;
      width: 100%;
      text-align: left;
      padding: 1.25rem 1.35rem;
      border: 1px solid rgba(30, 42, 56, 0.08);
      border-radius: 14px;
      background: #fff;
      box-shadow: var(--card-shadow);
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
      font: inherit;
      color: inherit;
    }
    .dash-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--card-hover);
      border-color: rgba(26, 188, 156, 0.35);
    }
    .dash-card:focus-visible {
      outline: 2px solid var(--erp-mint);
      outline-offset: 2px;
    }
    .dash-card-top {
      display: flex;
      align-items: flex-start;
      gap: 1rem;
    }
    .dash-icon {
      flex-shrink: 0;
      width: 48px;
      height: 48px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 12px;
      background: linear-gradient(145deg, rgba(63, 87, 114, 0.12), rgba(26, 188, 156, 0.1));
      color: var(--erp-accent);
      font-size: 1.25rem;
    }
    .dash-card h2 {
      margin: 0 0 0.35rem;
      font-size: 1.05rem;
      font-weight: 600;
      color: var(--erp-ink);
    }
    .dash-card p {
      margin: 0;
      font-size: 0.82rem;
      line-height: 1.45;
      color: #5c6b7a;
    }
    .dash-card .chevron {
      margin-left: auto;
      align-self: center;
      color: #adb8c4;
      font-size: 0.85rem;
      transition: transform 0.2s ease, color 0.2s ease;
    }
    .dash-card:hover .chevron {
      color: var(--erp-mint);
      transform: translateX(4px);
    }
    .dash-hint {
      margin-top: 2rem;
      padding: 1rem 1.25rem;
      border-radius: 12px;
      background: rgba(63, 87, 114, 0.06);
      border: 1px solid rgba(30, 42, 56, 0.06);
      font-size: 0.85rem;
      color: #5c6b7a;
      display: flex;
      align-items: flex-start;
      gap: 0.65rem;
    }
    .dash-hint i { color: var(--erp-accent); margin-top: 0.15rem; }
    .dash-empty {
      text-align: center;
      padding: 2.5rem 1rem;
      color: #6b7c8d;
      font-size: 0.95rem;
    }
  </style>
</head>
<body>
  <div class="dash-wrap">
    <div class="dash-hero">
      <div class="dash-hero-inner">
        <h1>Welcome back<?= $displayName !== '' ? ', ' . $displayName : '' ?></h1>
        <p class="sub">Open a module below — same access as the sidebar. Your role and permissions are unchanged.</p>
        <?php if ($displayRole !== ''): ?>
          <div class="dash-badge">
            <i class="fas fa-id-badge" aria-hidden="true"></i>
            <span><?= $displayRole ?></span>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (count($modules) > 0): ?>
      <p class="dash-section-title">Quick access</p>
      <div class="dash-grid">
        <?php foreach ($modules as $m): ?>
          <button
            type="button"
            class="dash-card"
            onclick="parent.document.querySelector('.tabbtn[data-tab-id=<?= htmlspecialchars($m['id'], ENT_QUOTES, 'UTF-8') ?>]')?.click()"
          >
            <div class="dash-card-top">
              <div class="dash-icon" aria-hidden="true">
                <i class="fas <?= htmlspecialchars($m['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
              </div>
              <div class="flex-grow-1">
                <h2><?= htmlspecialchars($m['label'], ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars($m['desc'], ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <span class="chevron" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
            </div>
          </button>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="dash-empty">No modules are available for your account. Contact an administrator if this is unexpected.</p>
    <?php endif; ?>

    <div class="dash-hint">
      <i class="fas fa-circle-info" aria-hidden="true"></i>
      <span>Use the left sidebar for the full menu and sub-pages. Quick access here opens the same tab as clicking the sidebar.</span>
    </div>
  </div>
</body>
</html>
