<?php
// ============================================================
//  billing.php  –  Generate Bill, Print Bill, Payment
// ============================================================
require_once 'config.php';
requireLogin();
$db  = getDB();
$msg = '';
$err = '';
$esewa_redirect = null;
$esewa_auto_submit = false;

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');

if (isset($_GET['success'])) $msg = htmlspecialchars($_GET['success']);
if (isset($_GET['error']))   $err = htmlspecialchars($_GET['error']);

// ── FINALIZE PAYMENT ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize'])) {
  $order_id       = (int)$_POST['order_id'];
  $payment_method = $_POST['payment_method'] ?? 'cash';
  $tax_percent    = 13.00;

  $res   = $db->query("SELECT SUM(quantity * unit_price) AS sub FROM order_items WHERE order_id=$order_id");
  $sub   = (float)$res->fetch_assoc()['sub'];
  $tax   = round($sub * $tax_percent / 100, 2);
  $total = $sub + $tax;

  // Load or create bill
  $bill_row = null;
  $bill_stmt = $db->prepare('SELECT * FROM bills WHERE order_id=? LIMIT 1');
  $bill_stmt->bind_param('i', $order_id);
  $bill_stmt->execute();
  $bill_res = $bill_stmt->get_result();
  if ($bill_res && $bill_res->num_rows > 0) {
    $bill_row = $bill_res->fetch_assoc();
  }
  $bill_stmt->close();

  $pay_status = ($payment_method === 'esewa') ? 'pending' : 'success';
  $esewa_pid  = $bill_row['esewa_pid'] ?? null;
  if ($payment_method === 'esewa' && !$esewa_pid) {
    $esewa_pid = 'BILL-' . $order_id . '-' . time();
  }

  if ($bill_row) {
    $upd = $db->prepare('UPDATE bills
      SET subtotal=?, tax_percent=?, tax_amount=?, total_amount=?, payment_method=?, payment_status=?, esewa_pid=?
      WHERE id=?');
    $upd->bind_param('ddddsssi', $sub, $tax_percent, $tax, $total, $payment_method, $pay_status, $esewa_pid, $bill_row['id']);
    $upd->execute();
    $upd->close();
  } else {
    $ins = $db->prepare('INSERT INTO bills (order_id, subtotal, tax_percent, tax_amount, total_amount, payment_method, payment_status, esewa_pid)
             VALUES (?,?,?,?,?,?,?,?)');
    $ins->bind_param('iddddsss', $order_id, $sub, $tax_percent, $tax, $total, $payment_method, $pay_status, $esewa_pid);
    $ins->execute();
    $ins->close();
  }

  if ($payment_method === 'esewa') {
    // Keep table occupied until callback verifies payment
    $db->query("UPDATE orders SET status='billed' WHERE id=$order_id");

    $esewa_auto_submit = true;
    $esewa_redirect = [
      'url'    => ESEWA_PAYMENT_URL,
      'fields' => [
        'amt'   => number_format($sub, 2, '.', ''),
        'pdc'   => 0,
        'psc'   => 0,
        'txAmt' => number_format($tax, 2, '.', ''),
        'tAmt'  => number_format($total, 2, '.', ''),
        'pid'   => $esewa_pid,
        'scd'   => ESEWA_MERCHANT_CODE,
        'su'    => $base_url . '/esewa_verify.php?pid=' . urlencode($esewa_pid),
        'fu'    => $base_url . '/esewa_verify.php?status=failed&pid=' . urlencode($esewa_pid),
      ],
    ];
  } else {
    $db->query("UPDATE orders SET status='paid' WHERE id=$order_id");
    $db->query("UPDATE restaurant_tables t
          JOIN orders o ON o.table_id=t.id
          SET t.status='available' WHERE o.id=$order_id");

    header("Location: billing.php?print=$order_id"); exit;
  }
}

// ── LOAD ORDER FOR BILLING ───────────────────────────────
$order    = null;
$items    = [];
$bill     = null;
$order_id = (int)($_GET['order_id'] ?? $_GET['print'] ?? 0);
$print_mode = isset($_GET['print']);

if ($order_id > 0) {
    $stmt = $db->prepare("
        SELECT o.*, t.table_name, w.name AS waiter_name
        FROM orders o
        JOIN restaurant_tables t ON t.id=o.table_id
        LEFT JOIN waiters w ON w.id=o.waiter_id
        WHERE o.id=?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($order) {
        $items = $db->query("
            SELECT oi.quantity, oi.unit_price, m.item_name
            FROM order_items oi JOIN menu_items m ON m.id=oi.menu_item_id
            WHERE oi.order_id=$order_id
        ")->fetch_all(MYSQLI_ASSOC);

        $bill_res = $db->query("SELECT * FROM bills WHERE order_id=$order_id");
        if ($bill_res->num_rows > 0) $bill = $bill_res->fetch_assoc();
    }
}

// Subtotal calc (for form display before payment)
$subtotal = 0;
foreach ($items as $it) $subtotal += $it['quantity'] * $it['unit_price'];
$tax_amount = round($subtotal * 0.13, 2);
$grand_total = $subtotal + $tax_amount;

if ($bill && $bill['payment_method'] === 'esewa' && $bill['payment_status'] === 'pending' && !$esewa_redirect) {
  $esewa_redirect = [
    'url'    => ESEWA_PAYMENT_URL,
    'fields' => [
      'amt'   => number_format($bill['subtotal'], 2, '.', ''),
      'pdc'   => 0,
      'psc'   => 0,
      'txAmt' => number_format($bill['tax_amount'], 2, '.', ''),
      'tAmt'  => number_format($bill['total_amount'], 2, '.', ''),
      'pid'   => $bill['esewa_pid'],
      'scd'   => ESEWA_MERCHANT_CODE,
      'su'    => $base_url . '/esewa_verify.php?pid=' . urlencode($bill['esewa_pid']),
      'fu'    => $base_url . '/esewa_verify.php?status=failed&pid=' . urlencode($bill['esewa_pid']),
    ],
  ];
}

// Fetch open orders list for sidebar selection
$open_orders = $db->query("
    SELECT o.id, t.table_name, SUM(oi.quantity*oi.unit_price) AS total
    FROM orders o
    JOIN restaurant_tables t ON t.id=o.table_id
    LEFT JOIN order_items oi ON oi.order_id=o.id
    WHERE o.status='open'
    GROUP BY o.id
")->fetch_all(MYSQLI_ASSOC);

$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>Billing – NepDine</title>
  <link rel="stylesheet" href="style.css"/>
  <style>
    @media print {
      .sidebar, .no-print { display: none !important; }
      .main { margin: 0 !important; padding: 1rem !important; }
      .bill-receipt { box-shadow: none; border: 1px solid #ccc; }
    }
  </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main">
  <h1>Billing</h1>

  <?php if ($msg): ?><div class="alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <!-- ── Select Open Order ── -->
  <?php if (!$order_id || !$order): ?>
  <div class="form-card no-print">
    <h3>Select Order to Bill</h3>
    <?php if (empty($open_orders)): ?>
      <p class="no-data">No open orders. <a href="orders.php">Place an order first.</a></p>
    <?php else: ?>
    <form method="GET" action="billing.php" class="inline-form">
      <select name="order_id" class="input-field" required>
        <option value="">-- Select Order --</option>
        <?php foreach ($open_orders as $oo): ?>
          <option value="<?= $oo['id'] ?>">
            Order #<?= $oo['id'] ?> – <?= htmlspecialchars($oo['table_name']) ?>
            (Rs. <?= number_format($oo['total'], 2) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn-primary">Load Order</button>
    </form>
    <?php endif; ?>
  </div>

  <?php else: ?>

  <!-- ── Bill Receipt ── -->
  <div class="bill-receipt" id="billReceipt">
    <div class="receipt-header">
      <h2>🍽 NEPDINE Restaurant</h2>
      <p>Smart Dining, Great Taste</p>
      <hr/>
    </div>

    <div class="receipt-meta">
      <div><strong>Bill #:</strong> <?= $bill ? $bill['id'] : 'PREVIEW' ?></div>
      <div><strong>Order #:</strong> <?= $order['id'] ?></div>
      <div><strong>Table:</strong> <?= htmlspecialchars($order['table_name']) ?></div>
      <div><strong>Waiter:</strong> <?= htmlspecialchars($order['waiter_name'] ?: 'N/A') ?></div>
      <div><strong>Date:</strong> <?= date('d M Y, h:i A') ?></div>
    </div>
    <hr/>

    <table class="receipt-table">
      <thead>
        <tr><th>Item</th><th>Qty</th><th>Price</th><th>Amount</th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): $amt = $it['quantity'] * $it['unit_price']; ?>
        <tr>
          <td><?= htmlspecialchars($it['item_name']) ?></td>
          <td><?= $it['quantity'] ?></td>
          <td>Rs. <?= number_format($it['unit_price'], 2) ?></td>
          <td>Rs. <?= number_format($amt, 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <hr/>

    <div class="receipt-totals">
      <div><span>Subtotal:</span><span>Rs. <?= number_format($bill ? $bill['subtotal'] : $subtotal, 2) ?></span></div>
      <div><span>VAT (13%):</span><span>Rs. <?= number_format($bill ? $bill['tax_amount'] : $tax_amount, 2) ?></span></div>
      <div class="grand-total"><span>TOTAL:</span><span>Rs. <?= number_format($bill ? $bill['total_amount'] : $grand_total, 2) ?></span></div>
      <?php if ($bill): ?>
      <div><span>Payment:</span><span><?= ucfirst($bill['payment_method']) ?></span></div>
      <div><span>Status:</span>
        <?php if ($bill['payment_status'] === 'success'): ?>
          <span class="badge badge-green">PAID</span>
        <?php elseif ($bill['payment_status'] === 'pending'): ?>
          <span class="badge badge-orange">PENDING</span>
        <?php else: ?>
          <span class="badge badge-orange">FAILED</span>
        <?php endif; ?>
      </div>
      <?php if (!empty($bill['esewa_ref_id'])): ?>
      <div><span>Txn ID:</span><span><?= htmlspecialchars($bill['esewa_ref_id']) ?></span></div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="receipt-footer">
      <p>Thank you for dining at NepDine!</p>
      <p>Please visit again 😊</p>
    </div>
  </div>

  <!-- ── Payment Form (only if not yet paid) ── -->
  <?php if (!$bill): ?>
  <div class="form-card no-print" style="margin-top:1.5rem;">
    <h3>Finalize Payment</h3>
    <form method="POST" action="billing.php" class="inline-form">
      <input type="hidden" name="order_id" value="<?= $order_id ?>"/>
      <select name="payment_method" class="input-field" required>
        <option value="cash">💵 Cash</option>
        <option value="card">💳 Card</option>
        <option value="esewa">📱 eSewa</option>
        <option value="khalti">📱 Khalti</option>
      </select>
      <button type="submit" name="finalize" class="btn-primary">Mark as Paid &amp; Generate Bill</button>
    </form>
  </div>
  <?php elseif ($bill && $bill['payment_method'] === 'esewa' && $bill['payment_status'] !== 'success'): ?>
  <div class="form-card no-print" style="margin-top:1.5rem;">
    <h3>Complete Payment via eSewa</h3>
    <p>Redirect to eSewa to pay Rs. <?= number_format($bill['total_amount'], 2) ?> for Order #<?= $order_id ?>.</p>
    <button type="button" class="btn-primary" onclick="document.getElementById('esewaPayForm')?.submit();">Pay with eSewa</button>
    <p style="margin-top:0.5rem;font-size:0.9rem;">If the payment was already completed, refresh or wait for the verification callback to mark the bill as paid.</p>
  </div>
  <?php endif; ?>

  <!-- ── Print Button ── -->
  <div class="no-print" style="margin-top:1rem;">
    <button onclick="window.print()" class="btn-secondary">🖨 Print Bill</button>
    <a href="billing.php" class="btn-secondary">← Back to Billing</a>
    <?php if ($bill): ?>
    <a href="orders.php" class="btn-primary">View Orders</a>
    <?php endif; ?>
  </div>

  <?php if ($esewa_redirect): ?>
  <form id="esewaPayForm" method="POST" action="<?= htmlspecialchars($esewa_redirect['url']) ?>" style="display:none;">
    <?php foreach ($esewa_redirect['fields'] as $key => $value): ?>
      <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>" />
    <?php endforeach; ?>
  </form>
  <?php if ($esewa_auto_submit): ?>
  <script>document.getElementById('esewaPayForm')?.submit();</script>
  <?php endif; ?>
  <?php endif; ?>

  <?php endif; ?>
</div>
</body>
</html>
