<?php
// ============================================================
//  menu.php  –  Menu CRUD (Add / Edit / Delete items + categories)
// ============================================================
require_once 'config.php';
requireLogin();
$db  = getDB();
$msg = '';
$err = '';

// ── DELETE item ──────────────────────────────────────────
if (isset($_GET['delete'])) {
    $did  = (int)$_GET['delete'];
    $stmt = $db->prepare('DELETE FROM menu_items WHERE id=?');
    $stmt->bind_param('i', $did);
    $stmt->execute() ? $msg = 'Item deleted.' : $err = 'Delete failed.';
    $stmt->close();
}

// ── TOGGLE availability ─────────────────────────────────
if (isset($_GET['toggle'])) {
    $tid  = (int)$_GET['toggle'];
    $stmt = $db->prepare('UPDATE menu_items SET available = 1 - available WHERE id=?');
    $stmt->bind_param('i', $tid);
    $stmt->execute();
    $stmt->close();
    header('Location: menu.php'); exit;
}

// ── ADD or UPDATE item ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id  = (int)($_POST['item_id']  ?? 0);
    $cat_id   = (int)($_POST['category'] ?? 0);
    $name     = trim($_POST['item_name'] ?? '');
    $price    = (float)($_POST['price']  ?? 0);

    if (empty($name)) {
        $err = 'Item name is required.';
    } elseif ($cat_id <= 0) {
        $err = 'Please select a category.';
    } elseif ($price <= 0) {
        $err = 'Price must be greater than 0.';
    } else {
        if ($item_id > 0) {
            // UPDATE
            $stmt = $db->prepare('UPDATE menu_items SET category_id=?, item_name=?, price=? WHERE id=?');
            $stmt->bind_param('isdi', $cat_id, $name, $price, $item_id);
            $stmt->execute() ? $msg = 'Item updated.' : $err = 'Update failed.';
            $stmt->close();
        } else {
            // INSERT
            $stmt = $db->prepare('INSERT INTO menu_items (category_id, item_name, price) VALUES (?,?,?)');
            $stmt->bind_param('isd', $cat_id, $name, $price);
            $stmt->execute() ? $msg = 'Item added.' : $err = 'Insert failed.';
            $stmt->close();
        }
    }
}

// Fetch edit item if requested
$edit_item = null;
if (isset($_GET['edit'])) {
    $eid  = (int)$_GET['edit'];
    $stmt = $db->prepare('SELECT * FROM menu_items WHERE id=?');
    $stmt->bind_param('i', $eid);
    $stmt->execute();
    $edit_item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch categories
$categories = $db->query('SELECT * FROM categories ORDER BY id')->fetch_all(MYSQLI_ASSOC);

// Fetch all items grouped by category
$items_result = $db->query('
    SELECT m.*, c.category_name
    FROM menu_items m
    JOIN categories c ON c.id = m.category_id
    ORDER BY c.id, m.item_name
');
$items_by_cat = [];
while ($row = $items_result->fetch_assoc()) {
    $items_by_cat[$row['category_name']][] = $row;
}
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>Menu – NepDine</title>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main">
  <h1>Menu Management</h1>

  <?php if ($msg): ?><div class="alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <!-- ── Add / Edit Form ── -->
  <div class="form-card">
    <h3><?= $edit_item ? 'Edit Item' : 'Add New Item' ?></h3>
    <form method="POST" action="menu.php" class="inline-form">
      <?php if ($edit_item): ?>
        <input type="hidden" name="item_id" value="<?= $edit_item['id'] ?>"/>
      <?php endif; ?>

      <select name="category" class="input-field" required>
        <option value="">-- Category --</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>"
            <?= ($edit_item && $edit_item['category_id'] == $cat['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['category_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <input type="text" name="item_name" class="input-field" placeholder="Item name" required
             value="<?= htmlspecialchars($edit_item['item_name'] ?? '') ?>"/>

      <input type="number" step="0.01" min="1" name="price" class="input-field" placeholder="Price (Rs.)" required
             value="<?= htmlspecialchars($edit_item['price'] ?? '') ?>"/>

      <button type="submit" class="btn-primary"><?= $edit_item ? 'Update' : 'Add Item' ?></button>
      <?php if ($edit_item): ?>
        <a href="menu.php" class="btn-secondary">Cancel</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- ── Menu Table grouped by category ── -->
  <?php foreach ($items_by_cat as $catName => $items): ?>
    <h2 class="menu-category"><?= htmlspecialchars($catName) ?></h2>
    <table class="data-table">
      <thead>
        <tr><th>#</th><th>Item Name</th><th>Price (Rs.)</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php $i = 1; foreach ($items as $item): ?>
        <tr class="<?= !$item['available'] ? 'row-unavailable' : '' ?>">
          <td><?= $i++ ?></td>
          <td><?= htmlspecialchars($item['item_name']) ?></td>
          <td>Rs. <?= number_format($item['price'], 2) ?></td>
          <td>
            <a href="menu.php?toggle=<?= $item['id'] ?>" class="badge <?= $item['available'] ? 'badge-green' : 'badge-red' ?>">
              <?= $item['available'] ? 'Available' : 'Unavailable' ?>
            </a>
          </td>
          <td class="action-btns">
            <a href="menu.php?edit=<?= $item['id'] ?>" class="btn-edit">Edit</a>
            <a href="menu.php?delete=<?= $item['id'] ?>" class="btn-delete"
               onclick="return confirm('Delete this item?')">Delete</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endforeach; ?>
</div>
</body>
</html>
