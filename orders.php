<?php
// ============================================================
//  orders.php  –  Take Orders / Manage Orders
// ============================================================
require_once 'config.php';
requireLogin();
$db  = getDB();
$msg = '';
$err = '';

// ── CANCEL ORDER ─────────────────────────────────────────
if (isset($_GET['cancel'])) {
    $oid  = (int)$_GET['cancel'];
    $stmt = $db->prepare("UPDATE orders SET status='cancelled' WHERE id=? AND status='open'");
    $stmt->bind_param('i', $oid);
    $stmt->execute() ? $msg = 'Order cancelled.' : $err = 'Could not cancel.';
    $stmt->close();
    // free table
    $db->query("UPDATE restaurant_tables t
                JOIN orders o ON o.table_id = t.id
                SET t.status = 'available'
                WHERE o.id = $oid");
}

// ── PLACE NEW ORDER ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $table_id  = (int)($_POST['table_id']  ?? 0);
    $waiter_id = (int)($_POST['waiter_id'] ?? 0) ?: null;
  $guest_count = (int)($_POST['guest_count'] ?? 1);
    $items     = $_POST['items'] ?? [];   // array[menu_item_id] = quantity

    // Validation
    if ($table_id <= 0) {
      $err = 'Please select a table.';
    } else {
      $capacity = null;
      $capStmt  = $db->prepare('SELECT capacity FROM restaurant_tables WHERE id=?');
      $capStmt->bind_param('i', $table_id);
      $capStmt->execute();
      $capStmt->bind_result($capacity);
      $capStmt->fetch();
      $capStmt->close();

      if ($capacity === null) {
        $err = 'Selected table not found.';
      } elseif ($guest_count < 1 || $guest_count > 50) {
        $err = 'Guest count must be between 1 and 50.';
      } elseif ($guest_count > $capacity) {
        $err = "Guest count exceeds table capacity of $capacity.";
      }
    }

    if (!$err) {
        // Filter out zero-qty items
        $ordered = [];
        foreach ($items as $mid => $qty) {
            $qty = (int)$qty;
            if ($qty > 0) $ordered[(int)$mid] = $qty;
        }
        if (empty($ordered)) {
            $err = 'Please select at least one item.';
        } else {
            // Create order
        $stmt = $db->prepare('INSERT INTO orders (table_id, waiter_id, guest_count) VALUES (?,?,?)');
        $stmt->bind_param('iii', $table_id, $waiter_id, $guest_count);
            $stmt->execute();
            $order_id = $db->insert_id;
            $stmt->close();

            // Insert order items
            $ins = $db->prepare('INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price)
                                 SELECT ?, id, ?, price FROM menu_items WHERE id=?');
            foreach ($ordered as $mid => $qty) {
                $ins->bind_param('iii', $order_id, $qty, $mid);
                $ins->execute();
            }
            $ins->close();

            // Mark table occupied
            $db->query("UPDATE restaurant_tables SET status='occupied' WHERE id=$table_id");

            $msg = "Order #$order_id placed successfully!";
            header("Location: orders.php?success=" . urlencode($msg)); exit;
        }
    }
}

if (isset($_GET['success'])) $msg = htmlspecialchars($_GET['success']);

// Fetch data for order form
$tables  = $db->query("SELECT * FROM restaurant_tables WHERE status='available' ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$waiters = $db->query('SELECT * FROM waiters ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$menu    = $db->query('SELECT m.*, c.category_name FROM menu_items m
                       JOIN categories c ON c.id=m.category_id
                       WHERE m.available=1 ORDER BY c.id, m.item_name')->fetch_all(MYSQLI_ASSOC);

$menu_by_cat = [];
foreach ($menu as $item) $menu_by_cat[$item['category_name']][] = $item;

// Fetch open/recent orders
$orders = $db->query("
    SELECT o.id, o.status, o.created_at,
           t.table_name, w.name AS waiter_name,
           SUM(oi.quantity * oi.unit_price) AS total
    FROM orders o
    JOIN restaurant_tables t ON t.id = o.table_id
    LEFT JOIN waiters w ON w.id = o.waiter_id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.status IN ('open','billed')
    GROUP BY o.id
    ORDER BY o.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>Orders – NepDine</title>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main">
  <h1>Order Management</h1>

  <?php if ($msg): ?><div class="alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <!-- ── Place New Order Form ── -->
  <div class="form-card">
    <h3>Place New Order</h3>
    <form method="POST" action="orders.php" id="orderForm">
      <div class="order-meta inline-form">
        <div>
          <label>Table <span class="req">*</span></label>
          <select name="table_id" class="input-field" required>
            <option value="">-- Select Available Table --</option>
            <?php foreach ($tables as $t): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['table_name']) ?> (cap: <?= $t['capacity'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Guests <span class="req">*</span></label>
          <input type="number" name="guest_count" class="input-field" min="1" max="50" value="1" required />
        </div>
        <div>
          <label>Waiter</label>
          <select name="waiter_id" class="input-field">
            <option value="">-- Assign Waiter --</option>
            <?php foreach ($waiters as $w): ?>
              <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <h4 style="margin-top:1rem;">Select Items</h4>
      <?php foreach ($menu_by_cat as $cat => $items): ?>
        <h5 class="menu-category" style="font-size:0.95rem;"><?= htmlspecialchars($cat) ?></h5>
        <div class="order-items-grid">
          <?php foreach ($items as $item): ?>
          <div class="order-item-card">
            <span class="item-label"><?= htmlspecialchars($item['item_name']) ?></span>
            <span class="item-price">Rs. <?= number_format($item['price'], 2) ?></span>
            <input type="number" name="items[<?= $item['id'] ?>]"
                   min="0" max="50" value="0" class="qty-input"
                   onchange="updateTotal()"/>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

      <div class="order-summary">
        <strong>Estimated Total: Rs. <span id="liveTotal">0.00</span></strong>
      </div>

      <button type="submit" name="place_order" class="btn-primary" style="margin-top:1rem;">
        Place Order
      </button>
    </form>
  </div>

  <!-- ── Active Orders Table ── -->
  <h2 style="margin-top:2rem;">Active Orders</h2>
  <table class="data-table">
    <thead>
      <tr><th>#</th><th>Table</th><th>Waiter</th><th>Total (Rs.)</th><th>Status</th><th>Time</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php if (empty($orders)): ?>
        <tr><td colspan="7" class="no-data">No active orders.</td></tr>
      <?php else: foreach ($orders as $o): ?>
      <tr>
        <td>#<?= $o['id'] ?></td>
        <td><?= htmlspecialchars($o['table_name']) ?></td>
        <td><?= htmlspecialchars($o['waiter_name'] ?: '—') ?></td>
        <td>Rs. <?= number_format($o['total'], 2) ?></td>
        <td><span class="badge <?= $o['status']==='open' ? 'badge-green' : 'badge-orange' ?>"><?= ucfirst($o['status']) ?></span></td>
        <td><?= date('h:i A', strtotime($o['created_at'])) ?></td>
        <td class="action-btns">
          <a href="billing.php?order_id=<?= $o['id'] ?>" class="btn-primary" style="padding:0.3rem 0.7rem;font-size:0.8rem;">Bill</a>
          <?php if ($o['status']==='open'): ?>
          <a href="orders.php?cancel=<?= $o['id'] ?>" class="btn-delete"
             onclick="return confirm('Cancel this order?')">Cancel</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<script>
// Live price data for total calculation
const prices = {
  <?php foreach ($menu as $item): ?>
  <?= $item['id'] ?>: <?= $item['price'] ?>,
  <?php endforeach; ?>
};

function updateTotal() {
  let total = 0;
  document.querySelectorAll('.qty-input').forEach(input => {
    const mid = input.name.match(/\d+/)[0];
    total += (parseInt(input.value) || 0) * (prices[mid] || 0);
  });
  document.getElementById('liveTotal').textContent = total.toFixed(2);
}
</script>
</body>
</html>
