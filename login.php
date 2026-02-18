<?php
// ============================================================
//  login.php  –  Login page with PHP session auth
// ============================================================
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in → go to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // ── Server-side validation ──
    if (empty($email)) {
        $error = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (empty($password)) {
        $error = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, name, password, role FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id, $name, $hash, $role);
            $stmt->fetch();
            if (password_verify($password, $hash)) {
                $_SESSION['user_id']   = $id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_role'] = $role;
                header('Location: index.php');
                exit;
            } else {
                $error = 'Incorrect password.';
            }
        } else {
            $error = 'No account found with that email.';
        }
        $stmt->close();
        $db->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login – NepDine</title>
  <link rel="stylesheet" href="login.css"/>
</head>
<body>
<div class="container">
  <div class="left">
    <div class="logo">
      <div class="logo-circle"></div>
      <h2>Nepdine</h2>
    </div>

    <h1 class="welcome-title">WELCOME <span class="highlight">BACK</span></h1>
    <p class="subtitle">Welcome back! Please enter your details.</p>

    <?php if ($error): ?>
      <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>
      <div class="input">
        <label class="email">Email</label>
        <input type="email" name="email" placeholder="Enter your email"
               class="input-box" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/>

        <label class="email">Password</label>
        <input type="password" name="password" placeholder="••••••••"
               class="input-box" required/>
      </div>

      <div class="small-row">
        <div class="remember-me">
          <label for="remember-me">Remember me</label>
          <input type="checkbox" id="remember-me" name="remember"/>
        </div>
        <a href="#">Forgot password?</a>
      </div>

      <button type="submit" class="btn-login">Login</button>
    </form>

    <div class="auth-section">
      <div class="divider"><span>or</span></div>
      <p class="signup-text">
        Don't have an account? <a href="signup.php">Sign up</a>
      </p>
    </div>
  </div>
</div>
</body>
</html>
