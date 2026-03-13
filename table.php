<?php
// ============================================================
//  table.php  –  Restaurant Tables CRUD
// ============================================================
require_once 'config.php';
requireLogin();
$db  = getDB();
$msg = '';
$err = '';

// ── DELETE ───────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $did  = (int)$_GET['delete'];
    // Prevent deleting occupied tables
    $chk  = $db->prepare("SELECT status FROM restaurant_tables WHERE id=?");
    $chk->bind_param('i', $did);
    $chk->execute();
    $row  = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($row && $row['status'] === 'occupied') {
        $err = 'Cannot delete an occupied table.';
    } else {
        $stmt = $db->prepare('DELETE FROM restaurant_tables WHERE id=?');
        $stmt->bind_param('i', $did);
        $stmt->execute() ? $msg = 'Table removed.' : $err = 'Delete failed.';
        $stmt->close();
    }
}

// ── CHANGE STATUS ────────────────────────────────────────
if (isset($_GET['status']) && isset($_GET['id'])) {
    $sid    = (int)$_GET['id'];
    $status = $_GET['status'];
    $allowed = ['available', 'occupied', 'reserved'];
    if (in_array($status, $allowed)) {
        $stmt = $db->prepare('UPDATE restaurant_tables SET status=? WHERE id=?');
        $stmt->bind_param('si', $status, $sid);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: table.php'); exit;
}

// ── ADD / UPDATE ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tid      = (int)($_POST['table_id']   ?? 0);
    $tname    = trim($_POST['table_name']  ?? '');
    $capacity = (int)($_POST['capacity']   ?? 4);

    if (empty($tname)) {
        $err = 'Table name is required.';
    } elseif ($capacity < 1 || $capacity > 50) {
        $err = 'Capacity must be between 1 and 50.';
    } else {
        if ($tid > 0) {
            $stmt = $db->prepare('UPDATE restaurant_tables SET table_name=?, capacity=? WHERE id=?');
            $stmt->bind_param('sii', $tname, $capacity, $tid);
            $stmt->execute() ? $msg = 'Table updated.' : $err = $db->error;
            $stmt->close();
        } else {
            $stmt = $db->prepare('INSERT INTO restaurant_tables (table_name, capacity) VALUES (?,?)');
            $stmt->bind_param('si', $tname, $capacity);
            $stmt->execute() ? $msg = 'Table added.' : $err = $db->error;
            $stmt->close();
        }
    }
}

// Fetch edit record
$edit = null;
if (isset($_GET['edit'])) {
    $eid  = (int)$_GET['edit'];
    $stmt = $db->prepare('SELECT * FROM restaurant_tables WHERE id=?');
    $stmt->bind_param('i', $eid);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$tables = $db->query('SELECT * FROM restaurant_tables ORDER BY id')->fetch_all(MYSQLI_ASSOC);

// Map current guest count for active orders (latest per table)
$guestRows = $db->query("SELECT table_id, guest_count FROM orders WHERE status IN ('open','billed') ORDER BY id DESC")
         ->fetch_all(MYSQLI_ASSOC);
$guestMap = [];
foreach ($guestRows as $gr) {
  if (!isset($guestMap[$gr['table_id']])) {
    $guestMap[$gr['table_id']] = (int)$gr['guest_count'];
  }
}

$db->close();

$statusColors = ['available' => 'badge-green', 'occupied' => 'badge-red', 'reserved' => 'badge-orange'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>Tables – NepDine</title>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main">
  <h1>Table Management</h1>

  <?php if ($msg): ?><div class="alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div class="form-card">
    <h3><?= $edit ? 'Edit Table' : 'Add New Table' ?></h3>
    <form method="POST" action="table.php" class="inline-form">
      <?php if ($edit): ?>
        <input type="hidden" name="table_id" value="<?= $edit['id'] ?>"/>
      <?php endif; ?>

      <input type="text" name="table_name" class="input-field" placeholder="e.g. Table 6" required
             value="<?= htmlspecialchars($edit['table_name'] ?? '') ?>"/>

      <input type="number" name="capacity" class="input-field" placeholder="Capacity" min="1" max="50" required
             value="<?= htmlspecialchars($edit['capacity'] ?? 4) ?>"/>

      <button type="submit" class="btn-primary"><?= $edit ? 'Update' : 'Add Table' ?></button>
      <?php if ($edit): ?><a href="table.php" class="btn-secondary">Cancel</a><?php endif; ?>
    </form>
  </div>

  <!-- ── Table Grid cards ── -->
  <div class="table-grid">
    <?php foreach ($tables as $t): ?>
    <?php $guests = $guestMap[$t['id']] ?? 0; ?>
    <div class="table-card status-<?= $t['status'] ?>">
      <div class="table-card-header">
        <strong><?= htmlspecialchars($t['table_name']) ?></strong>
        <span class="badge <?= $statusColors[$t['status']] ?>"><?= ucfirst($t['status']) ?></span>
      </div>
      <div class="table-card-body">
        <span>👥 Capacity: <?= $t['capacity'] ?></span>
        <span>🧍 Guests: <?= $guests ?> / <?= $t['capacity'] ?></span>
      </div>
      <div class="table-card-actions">
        <div class="status-select">
          <?php foreach (['available','occupied','reserved'] as $s): ?>
            <a href="table.php?id=<?= $t['id'] ?>&status=<?= $s ?>"
               class="btn-status <?= $t['status']===$s ? 'active' : '' ?>"><?= ucfirst($s) ?></a>
          <?php endforeach; ?>
        </div>
        <div>
          <a href="table.php?edit=<?= $t['id'] ?>" class="btn-edit">Edit</a>
          <a href="table.php?delete=<?= $t['id'] ?>" class="btn-delete"
             onclick="return confirm('Delete this table?')">Delete</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
