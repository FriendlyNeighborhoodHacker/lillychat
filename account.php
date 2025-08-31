<?php
require_once __DIR__.'/partials.php';
require_login();

$u = current_user();
$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $first = trim($_POST['first_name'] ?? '');
  $last  = trim($_POST['last_name'] ?? '');
  $email = strtolower(trim($_POST['email'] ?? ''));

  if ($first === '' || $last === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = 'Please provide first name, last name, and a valid email.';
  } else {
    try {
      $st = pdo()->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?');
      $st->execute([$first, $last, $email, (int)$u['id']]);
      // Refresh current_user cache by resetting session uid (or reload)
      $_SESSION['uid'] = (int)$u['id'];
      $msg = 'Profile updated.';
      // reload latest values
      $ref = pdo()->prepare('SELECT * FROM users WHERE id = ?');
      $ref->execute([(int)$u['id']]);
      $u = $ref->fetch();
    } catch (PDOException $e) {
      if ((int)$e->errorInfo[1] === 1062) {
        $err = 'That email is already in use.';
      } else {
        $err = 'Failed to update profile.';
      }
    } catch (Throwable $e) {
      $err = 'Failed to update profile.';
    }
  }
}

header_html('My Profile');
?>
<h2>My Profile</h2>
<?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <form method="post" class="stack">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <label>First name
      <input type="text" name="first_name" value="<?=h($u['first_name'])?>" required>
    </label>
    <label>Last name
      <input type="text" name="last_name" value="<?=h($u['last_name'])?>" required>
    </label>
    <label>Email
      <input type="email" name="email" value="<?=h($u['email'])?>" required>
    </label>
    <div style="display:flex; gap:8px;">
      <button type="submit">Save</button>
      <a class="button secondary" href="/index.php">Cancel</a>
    </div>
  </form>
</div>
<?php footer_html(); ?>
