<?php
// ============================================================
//  index.php  –  Dashboard
// ============================================================
require_once 'config.php';
requireLogin();
$db = getDB();

// Fetch summary counts
$tables_total    = $db->query('SELECT COUNT(*) FROM restaurant_tables')->fetch_row()[0];
$tables_occupied = $db->query("SELECT COUNT(*) FROM restaurant_tables WHERE status='occupied'")->fetch_row()[0];
$waiters_total   = $db->query('SELECT COUNT(*) FROM waiters')->fetch_row()[0];
$menu_total      = $db->query("SELECT COUNT(*) FROM menu_items WHERE available=1")->fetch_row()[0];
$open_orders     = $db->query("SELECT COUNT(*) FROM orders WHERE status='open'")->fetch_row()[0];
$today_revenue   = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM bills WHERE DATE(generated_at)=CURDATE()")->fetch_row()[0];
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>Dashboard – NepDine</title>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main">
  <div class="home-wrapper">
    <div class="home-text">
      <h1 class="animated-title">Welcome to <span class="brand">NEPDINE</span></h1>
      <p class="tagline">Smart Restaurant Management System</p>
      <p class="subtitle">Use the sidebar to manage orders, staff, tables, and billing.</p>

      <div class="stats-grid">
        <div class="stat-card">
          <span class="stat-num"><?= $tables_total ?></span>
          <span class="stat-label">Total Tables</span>
        </div>
        <div class="stat-card occupied">
          <span class="stat-num"><?= $tables_occupied ?></span>
          <span class="stat-label">Occupied</span>
        </div>
        <div class="stat-card">
          <span class="stat-num"><?= $waiters_total ?></span>
          <span class="stat-label">Waiters</span>
        </div>
        <div class="stat-card">
          <span class="stat-num"><?= $menu_total ?></span>
          <span class="stat-label">Menu Items</span>
        </div>
        <div class="stat-card open">
          <span class="stat-num"><?= $open_orders ?></span>
          <span class="stat-label">Open Orders</span>
        </div>
        <div class="stat-card revenue">
          <span class="stat-num">Rs.<?= number_format($today_revenue, 2) ?></span>
          <span class="stat-label">Today's Revenue</span>
        </div>
      </div>
    </div>
    <div class="home-image">
      <img src="logo.png" alt="Restaurant logo" onerror="this.style.display='none'"/>
    </div>
  </div>
</div>
<script src="script.js"></script>
</body>
</html>
