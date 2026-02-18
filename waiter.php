<?php
// ============================================================
//  waiter.php  –  Waiter CRUD
// ============================================================
require_once 'config.php';
requireLogin();
$db  = getDB();
$msg = '';
$err = '';

// ── DELETE ───────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $did  = (int)$_GET['delete'];
    $stmt = $db->prepare('DELETE FROM waiters WHERE id=?');
    $stmt->bind_param('i', $did);
    $stmt->execute() ? $msg = 'Waiter removed.' : $err = 'Delete failed.';
    $stmt->close();
}

// ── ADD / UPDATE ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wid   = (int)($_POST['waiter_id'] ?? 0);
    $name  = trim($_POST['name']       ?? '');
    $email = trim($_POST['email']      ?? '');
    $phone = trim($_POST['phone']      ?? '');

    if (empty($name)) {
        $err = 'Name is required.';
    } elseif (strlen($name) < 2) {
        $err = 'Name must be at least 2 characters.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Valid email is required.';
    } elseif (!empty($phone) && !preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) {
        $err = 'Enter a valid phone number.';
    } else {
        if ($wid > 0) {
            $stmt = $db->prepare('UPDATE waiters SET name=?, email=?, phone=? WHERE id=?');
            $stmt->bind_param('sssi', $name, $email, $phone, $wid);
            $stmt->execute() ? $msg = 'Waiter updated.' : $err = $db->error;
            $stmt->close();
        } else {
            // Check duplicate email
            $chk = $db->prepare('SELECT id FROM waiters WHERE email=?');
            $chk->bind_param('s', $email);
            $chk->execute(); $chk->store_result();
            if ($chk->num_rows > 0) {
                $err = 'A waiter with this email already exists.';
            } else {
                $stmt = $db->prepare('INSERT INTO waiters (name, email, phone) VALUES (?,?,?)');
                $stmt->bind_param('sss', $name, $email, $phone);
                $stmt->execute() ? $msg = 'Waiter added.' : $err = $db->error;
                $stmt->close();
            }
            $chk->close();
        }
    }
}

// Fetch edit record
$edit = null;
if (isset($_GET['edit'])) {
    $eid  = (int)$_GET['edit'];
    $stmt = $db->prepare('SELECT * FROM waiters WHERE id=?');
    $stmt->bind_param('i', $eid);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$waiters = $db->query('SELECT * FROM waiters ORDER BY id DESC')->fetch_all(MYSQLI_ASSOC);
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>Waiters – NepDine</title>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main">
  <h1>Waiter Management</h1>

  <?php if ($msg): ?><div class="alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div class="form-card">
    <h3><?= $edit ? 'Edit Waiter' : 'Add New Waiter' ?></h3>
    <form method="POST" action="waiter.php" class="inline-form">
      <?php if ($edit): ?>
        <input type="hidden" name="waiter_id" value="<?= $edit['id'] ?>"/>
      <?php endif; ?>

      <input type="text" name="name" class="input-field" placeholder="Full Name" required
             value="<?= htmlspecialchars($edit['name'] ?? '') ?>"/>

      <input type="email" name="email" class="input-field" placeholder="Email" required
             value="<?= htmlspecialchars($edit['email'] ?? '') ?>"/>

      <input type="text" name="phone" class="input-field" placeholder="Phone (optional)"
             value="<?= htmlspecialchars($edit['phone'] ?? '') ?>"/>

      <button type="submit" class="btn-primary"><?= $edit ? 'Update' : 'Add Waiter' ?></button>
      <?php if ($edit): ?><a href="waiter.php" class="btn-secondary">Cancel</a><?php endif; ?>
    </form>
  </div>

  <table class="data-table">
    <thead>
      <tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Joined</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php if (empty($waiters)): ?>
        <tr><td colspan="6" class="no-data">No waiters added yet.</td></tr>
      <?php else: $i = 1; foreach ($waiters as $w): ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($w['name']) ?></td>
        <td><?= htmlspecialchars($w['email']) ?></td>
        <td><?= htmlspecialchars($w['phone'] ?: '—') ?></td>
        <td><?= date('d M Y', strtotime($w['created_at'])) ?></td>
        <td class="action-btns">
          <a href="waiter.php?edit=<?= $w['id'] ?>" class="btn-edit">Edit</a>
          <a href="waiter.php?delete=<?= $w['id'] ?>" class="btn-delete"
             onclick="return confirm('Remove this waiter?')">Delete</a>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
