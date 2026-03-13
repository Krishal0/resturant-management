<?php
// ============================================================
//  signup.php  –  Registration with server-side validation
// ============================================================
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name     = trim($_POST['name']             ?? '');
  $email    = trim($_POST['email']            ?? '');
  // Allow explicit username field; otherwise fall back to email to satisfy NOT NULL constraint
  $username = trim($_POST['username'] ?? $email);
  $password = trim($_POST['password']         ?? '');
  $confirm  = trim($_POST['confirm_password'] ?? '');

    // ── Validation ──
    if (empty($name)) {
        $error = 'Name is required.';
    } elseif (strlen($name) < 2) {
        $error = 'Name must be at least 2 characters.';
    } elseif (empty($email)) {
        $error = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (empty($username)) {
      $error = 'Username is required.';
    } elseif (strlen($username) < 3) {
      $error = 'Username must be at least 3 characters.';
    } elseif (empty($password)) {
        $error = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db   = getDB();
        // Check duplicate email
        $chk  = $db->prepare('SELECT id FROM users WHERE email = ?');
        $chk->bind_param('s', $email);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $error = 'An account with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins  = $db->prepare('INSERT INTO users (name, username, email, password) VALUES (?, ?, ?, ?)');
            $ins->bind_param('ssss', $name, $username, $email, $hash);
            if ($ins->execute()) {
                $success = 'Account created! <a href="login.php">Login now</a>.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
            $ins->close();
        }
        $chk->close();
        $db->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign Up – NepDine</title>
  <link rel="stylesheet" href="signup.css"/>
</head>
<body class="registration-body">
<div class="main-card">
  <div class="left-panel">
    <a href="login.php" class="back-arrow-link">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="m15 18-6-6 6-6"/>
      </svg>
    </a>

    <div class="welcome-text-container">
      <h1 class="welcome-title">
        <span class="text-dark">WELCOME</span>
        <span class="text-orange">TO NEPDINE</span>
      </h1>
      <p class="welcome-subtitle">Your appetite deserves VIP access. Join our community today!</p>
    </div>

    <?php if ($error): ?>
      <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" action="signup.php" class="registration-form" novalidate>
      <div class="form-field">
        <label class="input-label">Full Name</label>
        <input type="text" name="name" class="input-field" placeholder="John Doe"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required/>
      </div>

      <div class="form-field">
        <label class="input-label">Email</label>
        <input type="email" name="email" class="input-field" placeholder="user@gmail.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/>
      </div>

      <div class="form-field">
        <label class="input-label">Password</label>
        <div class="password-wrapper">
          <input type="password" id="password" name="password" class="input-field"
                 placeholder="Min 8 chars, 1 uppercase, 1 number" required/>
          <span class="toggle-eye" onclick="togglePass('password','eye1')">
            <svg id="eye1" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="3"/>
              <path d="M2 12s4-8 10-8 10 8 10 8-4 8-10 8-10-8-10-8z"/>
            </svg>
          </span>
        </div>
      </div>

      <div class="form-field">
        <label class="input-label">Confirm Password</label>
        <div class="password-wrapper">
          <input type="password" id="confirm_password" name="confirm_password" class="input-field"
                 placeholder="Re-enter password" required/>
          <span class="toggle-eye" onclick="togglePass('confirm_password','eye2')">
            <svg id="eye2" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="3"/>
              <path d="M2 12s4-8 10-8 10 8 10 8-4 8-10 8-10-8-10-8z"/>
            </svg>
          </span>
        </div>
      </div>

      <button type="submit" class="register-btn">Create Account</button>
    </form>

    <p class="signup-text" style="margin-top:1rem;text-align:center;">
      Already have an account? <a href="login.php">Login</a>
    </p>
  </div>
</div>
<script>
  function togglePass(fieldId, iconId) {
    const f = document.getElementById(fieldId);
    f.type = f.type === 'password' ? 'text' : 'password';
  }
</script>
</body>
</html>
