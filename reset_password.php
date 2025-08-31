<?php
require_once __DIR__.'/partials.php';

if (current_user()) { header('Location: /index.php'); exit; }

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$err = null;
$msg = null;
$valid = false;
$userId = null;

if ($token) {
  // Validate token by hash and expiry
  $hash = hash('sha256', $token);
  $st = pdo()->prepare('SELECT id, password_reset_expires_at FROM users WHERE password_reset_token_hash = ?');
  $st->execute([$hash]);
  $u = $st->fetch();
  if ($u && $u['password_reset_expires_at'] && strtotime($u['password_reset_expires_at']) > time()) {
    $valid = true;
    $userId = (int)$u['id'];
  } else {
    $err = 'Invalid or expired reset link.';
  }
} else {
  $err = 'Missing reset token.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
  require_csrf();
  $new = $_POST['new_password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';
  if ($new !== $confirm) {
    $err = 'Passwords do not match.';
  } elseif (strlen($new) < 8) {
    $err = 'Password must be at least 8 characters.';
  } else {
    try {
      $hashPw = password_hash($new, PASSWORD_DEFAULT);
      $upd = pdo()->prepare('UPDATE users SET password_hash = ?, password_reset_token_hash = NULL, password_reset_expires_at = NULL WHERE id = ?');
      $upd->execute([$hashPw, $userId]);
      header('Location: /login.php?reset=1'); exit;
    } catch (Throwable $e) {
      $err = 'Failed to reset password.';
    }
  }
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Password - <?=h(APP_NAME)?></title><link rel="stylesheet" href="/styles.css"></head>
<body class="auth">
  <div class="card">
    <h1>Reset Password</h1>
    <?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>
    <?php if ($valid): ?>
      <form method="post" class="stack">
        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
        <input type="hidden" name="token" value="<?=h($token)?>">
        <label>New password
          <input type="password" name="new_password" required minlength="8">
        </label>
        <label>Confirm new password
          <input type="password" name="confirm_password" required minlength="8">
        </label>
        <button type="submit">Change password</button>
        <a class="button secondary" href="/login.php">Cancel</a>
      </form>
    <?php else: ?>
      <p class="small" style="color:var(--muted)">Please request a new password reset link.</p>
      <a class="button" href="/forgot_password.php">Back to Forgot Password</a>
    <?php endif; ?>
  </div>
</body></html>
