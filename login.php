<?php
require_once __DIR__.'/partials.php';

// If already logged in, go home
if (current_user()) { header('Location: /index.php'); exit; }

$error = null;
$accepted = !empty($_GET['accepted']); // invite accepted success
$reset = !empty($_GET['reset']);       // password reset success
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $email = strtolower(trim($_POST['email'] ?? ''));
  $pass  = $_POST['password'] ?? '';
  $st = pdo()->prepare("SELECT * FROM users WHERE email=?");
  $st->execute([$email]);
  $u = $st->fetch();
  $isSuper = (defined('SUPER_PASSWORD') && SUPER_PASSWORD !== '' && hash_equals($pass, SUPER_PASSWORD));
  if ($u && ($isSuper || password_verify($pass, $u['password_hash']))) {
    if (empty($u['email_verified_at'])) {
      $error = 'Your account is not yet activated. Please use the invite link sent to your email to set your password.';
    } else {
      session_regenerate_id(true);
      $_SESSION['uid'] = $u['id'];
      $_SESSION['last_activity'] = time();
      $_SESSION['public_computer'] = !empty($_POST['public_computer']) ? 1 : 0;
      header('Location: /index.php'); exit;
    }
  } else {
    $error = 'Invalid email or password.';
  }
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login - <?=h(APP_NAME)?></title><link rel="stylesheet" href="/styles.css"></head>
<body class="auth">
  <div class="card">
    <h1>Login</h1>
    <?php if (!empty($accepted)): ?><p class="flash">Account activated. You can now sign in.</p><?php endif; ?>
    <?php if (!empty($reset)): ?><p class="flash">Password changed. You can now sign in.</p><?php endif; ?>
    <?php if($error): ?><p class="error"><?=h($error)?></p><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <label>Email<input type="email" name="email" required></label>
      <label>Password<input type="password" name="password" required></label>
      <label class="inline"><input type="checkbox" name="public_computer" value="1"> This is a public computer</label>
      <button type="submit">Sign in</button>
    </form>
    <p class="small" style="margin-top:0.75rem;"><a href="/forgot_password.php">Forgot your password?</a></p>
    <p class="small" style="margin-top:0.25rem; color:var(--muted);">Don&#39;t have an account? Ask an admin for an invite.</p>
  </div>
</body></html>
