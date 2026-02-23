<?php
// ============================================================
//  includes/sidebar.php  –  shared sidebar partial
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$current = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
  <h2>NepDine</h2>
  <ul>
    <li><a href="index.php"   class="<?= $current==='index.php'  ?'active':'' ?>">🏠 Home</a></li>
    <li><a href="menu.php"    class="<?= $current==='menu.php'   ?'active':'' ?>">🍽 Menu</a></li>
    <li><a href="waiter.php"  class="<?= $current==='waiter.php' ?'active':'' ?>">🧑‍🍳 Waiter</a></li>
    <li><a href="table.php"   class="<?= $current==='table.php'  ?'active':'' ?>">🪑 Table</a></li>
    <li><a href="orders.php"  class="<?= $current==='orders.php' ?'active':'' ?>">📝 Orders</a></li>
    <li><a href="billing.php" class="<?= $current==='billing.php'?'active':'' ?>">💵 Billing</a></li>
    <li><a href="bills.php"   class="<?= $current==='bills.php'  ?'active':'' ?>">📜 Bill History</a></li>
    <li><a href="logout.php">🚪 Logout</a></li>
  </ul>
  <div class="sidebar-user">👤 <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></div>
</div>
