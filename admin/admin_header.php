<?php
// admin/admin_header.php - Shared Navigation Shell for PPSA Admin System

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requirePpsaLogin();

$currentUser = currentPpsaUser();
$db = getPpsaDb();

// Pending count badges
$pendingAthletes = 0;
$pendingOfficials = 0;
if ($db) {
    try {
        $pendingAthletes  = (int)$db->query("SELECT COUNT(*) FROM ppsa_athlete_applications WHERE status = 'pending'")->fetchColumn();
        $pendingOfficials = (int)$db->query("SELECT COUNT(*) FROM ppsa_official_applications WHERE status = 'pending'")->fetchColumn();
    } catch (\Throwable $e) {}
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle ?? 'PPSA Administration'); ?> — Punjab Para Sports</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --navy: #0E1F4B;
      --navy-dark: #081B4B;
      --navy-light: #162B66;
      --gold: #FFC400;
      --green: #00B074;
      --green-dark: #008f5d;
      --danger: #EF4444;
      --warning: #F59E0B;
      --surface: #F8FAFC;
      --border: #E2E8F0;
      --text: #0F172A;
      --text-muted: #64748B;
      --radius: 10px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F1F5F9; color: var(--text); min-height: 100vh; }
    
    /* Admin Top Bar */
    .admin-navbar {
      background: var(--navy);
      color: #fff;
      padding: 0 2rem;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 4px 15px rgba(8, 27, 75, 0.2);
      position: sticky;
      top: 0;
      z-index: 1000;
    }
    .admin-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: #fff;
    }
    .admin-brand-title {
      font-family: 'Outfit', sans-serif;
      font-weight: 800;
      font-size: 1.15rem;
      letter-spacing: 0.02em;
    }
    .admin-brand-title span { color: var(--gold); }
    .admin-brand-badge {
      font-size: 0.65rem;
      font-weight: 800;
      padding: 3px 8px;
      border-radius: 4px;
      background: rgba(255, 196, 0, 0.2);
      color: var(--gold);
      border: 1px solid rgba(255, 196, 0, 0.4);
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }
    
    /* Nav Links */
    .admin-menu {
      display: flex;
      align-items: center;
      gap: 6px;
      list-style: none;
    }
    .admin-menu-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: #CBD5E1;
      text-decoration: none;
      font-size: 0.88rem;
      font-weight: 600;
      padding: 8px 14px;
      border-radius: 8px;
      transition: all 0.2s ease;
    }
    .admin-menu-link:hover, .admin-menu-link.active {
      color: #fff;
      background: rgba(255, 255, 255, 0.12);
    }
    .admin-menu-link.active {
      background: var(--navy-light);
      border: 1px solid rgba(255, 255, 255, 0.15);
    }
    .badge-count {
      background: var(--danger);
      color: #fff;
      font-size: 0.7rem;
      font-weight: 800;
      padding: 2px 6px;
      border-radius: 999px;
    }

    /* Right User Profile */
    .admin-user-block {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .user-info {
      text-align: right;
    }
    .user-name {
      font-size: 0.85rem;
      font-weight: 700;
      color: #fff;
      display: block;
    }
    .user-role {
      font-size: 0.7rem;
      color: var(--gold);
      text-transform: uppercase;
      font-weight: 700;
      letter-spacing: 0.06em;
    }
    .btn-logout {
      background: rgba(239, 68, 68, 0.2);
      border: 1px solid rgba(239, 68, 68, 0.4);
      color: #FCA5A5;
      font-weight: 700;
      font-size: 0.8rem;
      padding: 6px 14px;
      border-radius: 6px;
      text-decoration: none;
      transition: all 0.2s ease;
    }
    .btn-logout:hover {
      background: var(--danger);
      color: #fff;
    }

    /* Layout Containers */
    .admin-layout {
      max-width: 1400px;
      margin: 0 auto;
      padding: 2rem 1.5rem 4rem;
    }
    .admin-page-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
    }
    .admin-page-title {
      font-family: 'Outfit', sans-serif;
      font-size: 1.7rem;
      font-weight: 800;
      color: var(--navy);
      letter-spacing: -0.01em;
    }
    .admin-page-subtitle {
      color: var(--text-muted);
      font-size: 0.95rem;
      margin-top: 4px;
    }

    /* Standard Card */
    .admin-card {
      background: #ffffff;
      border-radius: var(--radius);
      border: 1px solid var(--border);
      box-shadow: 0 4px 15px rgba(0,0,0,0.03);
      padding: 24px;
      margin-bottom: 24px;
    }
  </style>
</head>
<body>

<nav class="admin-navbar">
  <div style="display:flex;align-items:center;gap:24px;">
    <a href="dashboard.php" class="admin-brand">
      <div style="width:36px;height:36px;background:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#000;font-weight:900;font-size:16px;">P</div>
      <div>
        <div class="admin-brand-title">PUNJAB <span>PARA SPORTS</span></div>
      </div>
      <span class="admin-brand-badge">ADMIN PORTAL</span>
    </a>

    <ul class="admin-menu">
      <li><a href="dashboard.php" class="admin-menu-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
      <li>
        <a href="registrations.php" class="admin-menu-link <?php echo in_array($currentPage, ['registrations.php', 'athlete-details.php']) ? 'active' : ''; ?>">
          Athlete Intake
          <?php if ($pendingAthletes > 0): ?><span class="badge-count"><?php echo $pendingAthletes; ?></span><?php endif; ?>
        </a>
      </li>
      <li>
        <a href="officials.php" class="admin-menu-link <?php echo in_array($currentPage, ['officials.php', 'official-details.php']) ? 'active' : ''; ?>">
          Official Intake
          <?php if ($pendingOfficials > 0): ?><span class="badge-count"><?php echo $pendingOfficials; ?></span><?php endif; ?>
        </a>
      </li>
      <li><a href="athletes.php" class="admin-menu-link <?php echo in_array($currentPage, ['athletes.php', 'master-officials.php', 'master_officials.php']) ? 'active' : ''; ?>">Master Registry</a></li>
      <li><a href="audit-logs.php" class="admin-menu-link <?php echo $currentPage === 'audit-logs.php' ? 'active' : ''; ?>">Audit Logs</a></li>
      <li><a href="users.php" class="admin-menu-link <?php echo $currentPage === 'users.php' ? 'active' : ''; ?>">Staff Accounts</a></li>
    </ul>
  </div>

  <div class="admin-user-block">
    <div class="user-info">
      <span class="user-name"><?php echo htmlspecialchars($currentUser['full_name']); ?><?php if (!empty($currentUser['username'])): ?> <span style="font-weight:400;opacity:0.8;font-size:0.8rem;">(@<?php echo htmlspecialchars($currentUser['username']); ?>)</span><?php endif; ?></span>
      <span class="user-role"><?php echo htmlspecialchars($currentUser['role']); ?></span>
    </div>
    <a href="logout.php" class="btn-logout">Sign Out</a>
  </div>
</nav>

<div class="admin-layout">
