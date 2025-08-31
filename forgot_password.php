<?php
require_once __DIR__.'/partials.php';

if (current_user()) { header('Location: /index.php'); exit; }

$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $email = strtolower(trim($_POST['email'] ?? ''));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Show generic response to avoid user enumeration
    $msg = 'If an account exists, a reset email has been sent.';
  } else {
    try {
      $st = pdo()->prepare('SELECT id, first_name, last_name FROM users WHERE email = ?');
      $st->execute([$email]);
      $u = $st->fetch();

      // Always show same message regardless
      $msg = 'If an account exists, a reset email has been sent.';

      if ($u) {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        $upd = pdo()->prepare('UPDATE users SET password_reset_token_hash = ?, password_reset_expires_at = ? WHERE id = ?');
        $upd->execute([$hash, $expiresAt, $u['id']]);

        $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $link = $base . '/reset_password.php?token=' . urlencode($token);

        $siteTitle = get_setting('site_title', defined('APP_NAME') ? APP_NAME : 'LillyChat');
        $subject = 'Reset your password on ' . $siteTitle;
        $html = '<p>Hello '.htmlspecialchars($u['first_name'].' '.$u['last_name']).',</p>'
              . '<p>We received a request to reset your password. Click the button below to choose a new password:</p>'
              . '<p><a href="'.htmlspecialchars($link).'" style="display:inline-block;background:#f66b0e;color:#1a0b06;padding:10px 14px;border-radius:8px;text-decoration:none;font-weight:600;">Reset Password</a></p>'
              . '<p>If the button doesn\'t work, copy and paste this link into your browser:<br>'
              . htmlspecialchars($link).'</p>'
              . '<p>If you did not request this, you can safely ignore this email.</p>';

        @send_email($email, $subject, $html, $u['first_name'].' '.$u['last_name']);
      }
    } catch (Throwable $e) {
      // Still show generic success to avoid leaks
      $msg = 'If an account exists, a reset email has been sent.';
    }
  }
}

header_html('Forgot Password');
?>
<body class="auth">
  <div class="card">
    <h1>Forgot your password?</h1>
    <p class="small" style="color:var(--muted)">Enter your email and we&#39;ll send you a reset link.</p>
    <?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
    <?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>
    <form method="post" class="stack">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <label>Email
        <input type="email" name="email" required>
      </label>
      <button type="submit">Send reset link</button>
      <a class="button secondary" href="/login.php">Back to login</a>
    </form>
  </div>
</body>
<?php footer_html(); ?>
