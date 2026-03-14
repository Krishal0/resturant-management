<?php
// Manual confirmation endpoint for wallet payments in local/dev environments
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: billing.php');
    exit;
}

$db = getDB();
$order_id = (int)($_POST['order_id'] ?? 0);
$method   = strtolower(trim($_POST['payment_method'] ?? ''));
$txn_id   = trim($_POST['txn_id'] ?? '');

if ($order_id <= 0 || !in_array($method, ['esewa', 'khalti'], true)) {
    header('Location: billing.php?error=' . urlencode('Invalid payment confirmation request.'));
    exit;
}

$stmt = $db->prepare('SELECT b.id AS bill_id, o.table_id FROM bills b JOIN orders o ON o.id=b.order_id WHERE b.order_id=? LIMIT 1');
$stmt->bind_param('i', $order_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    header('Location: billing.php?error=' . urlencode('Bill not found for the order.'));
    exit;
}

$bill_id = (int)$row['bill_id'];
$table_id = (int)$row['table_id'];

if ($method === 'esewa') {
    $upd = $db->prepare("UPDATE bills SET payment_status='success', payment_method='esewa', esewa_ref_id=? WHERE id=?");
    $ref = $txn_id !== '' ? $txn_id : 'manual-esewa-' . time();
    $upd->bind_param('si', $ref, $bill_id);
} else {
    // Store Khalti txn in esewa_ref_id field to avoid another schema change.
    $upd = $db->prepare("UPDATE bills SET payment_status='success', payment_method='khalti', esewa_ref_id=? WHERE id=?");
    $ref = $txn_id !== '' ? $txn_id : 'manual-khalti-' . time();
    $upd->bind_param('si', $ref, $bill_id);
}
$upd->execute();
$upd->close();

$db->query("UPDATE orders SET status='paid' WHERE id=$order_id");
$db->query("UPDATE restaurant_tables SET status='available' WHERE id=$table_id");
$db->close();

header('Location: billing.php?print=' . $order_id . '&success=' . urlencode('Payment marked as paid.'));
exit;
?>
