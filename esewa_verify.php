<?php
// eSewa payment verification callback
require_once 'config.php';

$db = getDB();
$pid    = $_GET['pid']    ?? '';
$status = $_GET['status'] ?? '';
$refId  = $_GET['refId']  ?? '';
$amt    = $_GET['amt']    ?? '';

if ($pid === '') {
    exit('Invalid payment reference.');
}

$bill_stmt = $db->prepare('SELECT b.*, o.table_id FROM bills b JOIN orders o ON o.id = b.order_id WHERE b.esewa_pid = ? LIMIT 1');
$bill_stmt->bind_param('s', $pid);
$bill_stmt->execute();
$bill_res = $bill_stmt->get_result();
$bill     = $bill_res ? $bill_res->fetch_assoc() : null;
$bill_stmt->close();

if (!$bill) {
    exit('Bill not found for this payment.');
}

$order_id = (int)$bill['order_id'];
$table_id = (int)$bill['table_id'];

if ($status === 'failed') {
    $upd = $db->prepare("UPDATE bills SET payment_status='failed' WHERE id=?");
    $upd->bind_param('i', $bill['id']);
    $upd->execute();
    $upd->close();
    header('Location: billing.php?order_id=' . $order_id . '&error=' . urlencode('Payment cancelled. Please try again.'));
    exit;
}

if ($refId) {
    // Verify with eSewa
    $payload = http_build_query([
        'amt' => number_format($bill['total_amount'], 2, '.', ''),
        'rid' => $refId,
        'pid' => $pid,
        'scd' => ESEWA_MERCHANT_CODE,
    ]);

    $ch = curl_init(ESEWA_VERIFY_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        header('Location: billing.php?order_id=' . $order_id . '&error=' . urlencode('Verification failed: ' . $curl_err));
        exit;
    }

    if (stripos($response, 'success') !== false) {
        $upd = $db->prepare("UPDATE bills SET payment_status='success', payment_method='esewa', esewa_ref_id=? WHERE id=?");
        $upd->bind_param('si', $refId, $bill['id']);
        $upd->execute();
        $upd->close();

        $db->query("UPDATE orders SET status='paid' WHERE id=$order_id");
        $db->query("UPDATE restaurant_tables SET status='available' WHERE id=$table_id");

        header('Location: billing.php?print=' . $order_id . '&success=' . urlencode('eSewa payment verified.'));
        exit;
    }

    $upd = $db->prepare("UPDATE bills SET payment_status='failed' WHERE id=?");
    $upd->bind_param('i', $bill['id']);
    $upd->execute();
    $upd->close();

    header('Location: billing.php?order_id=' . $order_id . '&error=' . urlencode('Payment verification failed.'));
    exit;
}

// If no refId provided, show pending state
header('Location: billing.php?order_id=' . $order_id . '&error=' . urlencode('Awaiting payment confirmation.'));
exit;
?>
