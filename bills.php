<?php
// ============================================================
//  bills.php – Bill history listing with reprint links
// ============================================================
require_once 'config.php';
requireLogin();
$db = getDB();

// Fetch bills with order/table/waiter details
$sql = "
SELECT b.id AS bill_id,
       b.order_id,
       b.subtotal,
       b.tax_amount,
       b.total_amount,
       b.payment_method,
       b.generated_at,
       o.table_id,
       t.table_name,
       w.name AS waiter_name
FROM bills b
JOIN orders o           ON o.id = b.order_id
LEFT JOIN restaurant_tables t ON t.id = o.table_id
LEFT JOIN waiters w          ON w.id = o.waiter_id
ORDER BY b.generated_at DESC
";
$bills = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Bill History – NepDine</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main">
  <h1>Bill History</h1>
  <p style="color: var(--muted); margin-top:-6px;">All generated bills with quick reprint links.</p>

  <table class="data-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Bill ID</th>
        <th>Order</th>
        <th>Table</th>
        <th>Waiter</th>
        <th>Subtotal (Rs.)</th>
        <th>Tax (Rs.)</th>
        <th>Total (Rs.)</th>
        <th>Payment</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($bills)): ?>
        <tr><td colspan="11" class="no-data">No bills generated yet.</td></tr>
      <?php else: $i = 1; foreach ($bills as $b): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td>#<?= $b['bill_id'] ?></td>
          <td>Order #<?= $b['order_id'] ?></td>
          <td><?= htmlspecialchars($b['table_name'] ?? 'N/A') ?></td>
          <td><?= htmlspecialchars($b['waiter_name'] ?? 'N/A') ?></td>
          <td><?= number_format($b['subtotal'], 2) ?></td>
          <td><?= number_format($b['tax_amount'], 2) ?></td>
          <td><strong><?= number_format($b['total_amount'], 2) ?></strong></td>
          <td><span class="badge badge-orange" style="text-transform: capitalize;"><?= htmlspecialchars($b['payment_method']) ?></span></td>
          <td><?= date('d M Y, h:i A', strtotime($b['generated_at'])) ?></td>
          <td class="action-btns">
            <a class="btn-secondary" href="billing.php?print=<?= $b['order_id'] ?>" target="_blank">View / Print</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
