<?php
// Redirect customer/staff to selected wallet checkout for a pending bill
require_once 'config.php';
requireLogin();

$db = getDB();
$order_id = (int)($_GET['order_id'] ?? 0);
$method   = strtolower(trim($_GET['method'] ?? ''));

if ($order_id <= 0 || !in_array($method, ['esewa', 'khalti'], true)) {
    exit('Invalid payment request.');
}

$stmt = $db->prepare('SELECT b.*, o.id AS order_id FROM bills b JOIN orders o ON o.id=b.order_id WHERE b.order_id=? LIMIT 1');
$stmt->bind_param('i', $order_id);
$stmt->execute();
$bill = $stmt->get_result()->fetch_assoc();
$stmt->close();
$db->close();

if (!$bill) {
    exit('Bill not found.');
}

if ($bill['payment_status'] === 'success') {
    header('Location: billing.php?print=' . $order_id . '&success=' . urlencode('Bill is already marked as paid.'));
    exit;
}

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');

if ($method === 'esewa') {
    $pid = $bill['esewa_pid'];
    if (!$pid) {
        $pid = 'BILL-' . $order_id . '-' . time();
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8"/>
      <title>Redirecting to eSewa...</title>
    </head>
    <body>
      <p>Redirecting to eSewa checkout...</p>
      <form id="esewaForm" method="POST" action="<?= htmlspecialchars(ESEWA_PAYMENT_URL) ?>">
        <input type="hidden" name="amt" value="<?= number_format((float)$bill['subtotal'], 2, '.', '') ?>"/>
        <input type="hidden" name="pdc" value="0"/>
        <input type="hidden" name="psc" value="0"/>
        <input type="hidden" name="txAmt" value="<?= number_format((float)$bill['tax_amount'], 2, '.', '') ?>"/>
        <input type="hidden" name="tAmt" value="<?= number_format((float)$bill['total_amount'], 2, '.', '') ?>"/>
        <input type="hidden" name="pid" value="<?= htmlspecialchars($pid) ?>"/>
        <input type="hidden" name="scd" value="<?= htmlspecialchars(ESEWA_MERCHANT_CODE) ?>"/>
        <input type="hidden" name="su" value="<?= htmlspecialchars($base_url . '/esewa_verify.php?pid=' . urlencode($pid)) ?>"/>
        <input type="hidden" name="fu" value="<?= htmlspecialchars($base_url . '/esewa_verify.php?status=failed&pid=' . urlencode($pid)) ?>"/>
      </form>
      <script>document.getElementById('esewaForm').submit();</script>
    </body>
    </html>
    <?php
    exit;
}

// Khalti hosted checkout URL pattern (requires valid public key)
$khalti_amount_paisa = (int)round(((float)$bill['total_amount']) * 100);
$khalti_url = 'https://pay.khalti.com/?' . http_build_query([
    'public_key' => KHALTI_PUBLIC_KEY,
    'amount' => $khalti_amount_paisa,
    'product_identity' => 'order-' . $order_id,
    'product_name' => 'NepDine Bill #' . $order_id,
]);
header('Location: ' . $khalti_url);
exit;
?>
