<?php
require_once __DIR__.'/partials.php';

if (current_user()) { header('Location: /index.php'); exit; }

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$err = null;
$valid = false;
$user = null;

if ($token) {
  $st = pdo()->prepare('SELECT * FROM users WHERE invite_token = ?');
  $st->execute([$token]);
  $user = $st->fetch();
  if ($user && $user['invite_expires_at'] && strtotime($user['invite_expires_at']) > time()) {
    $valid = true;
  } else {
    $err = 'Invalid or expired invite link.';
  }
} else {
  $err = 'Missing invite token.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
  require_csrf();
  $password = $_POST['password'] ?? '';
  $confirm  = $_POST['confirm'] ?? '';
  if ($password !== $confirm) {
    $err = 'Passwords do not match.';
  } elseif (strlen($password) < 8) {
    $err = 'Password must be at least 8 characters.';
  } else {
    try {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $upd = pdo()->prepare('UPDATE users SET password_hash = ?, email_verified_at = NOW(), invite_token = NULL, invite_expires_at = NULL WHERE id = ?');
      $upd->execute([$hash, (int)$user['id']]);
      header('Location: /login.php?accepted=1'); exit;
    } catch (Throwable $e) {
      $err = 'Failed to activate account.';
    }
  }
}

header_html('Accept Invite');
?>
<body class="auth">
  <div class="card">
    <h1>Set your password</h1>
    <?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>
    <?php if ($valid && $user): ?>
      <p class="small" style="color:var(--muted)">Hi <?=h($user['first_name'].' '.$user['last_name'])?> (<?=h($user['email'])?>). Choose a password to activate your account.</p>
      <form method="post" class="stack">
        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
        <input type="hidden" name="token" value="<?=h($token)?>">
        <label>New password
          <input type="password" name="password" required minlength="8">
        </label>
        <label>Confirm password
          <input type="password" name="confirm" required minlength="8">
        </label>
        <button type="submit">Activate account</button>
        <a class="button secondary" href="/login.php">Cancel</a>
      </form>
    <?php else: ?>
      <p class="small" style="color:var(--muted)">Request a new invite from an admin.</p>
      <a class="button" href="/login.php">Back to login</a>
    <?php endif; ?>
  </div>
</body>
<?php footer_html(); ?>
