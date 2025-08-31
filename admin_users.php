<?php
require_once __DIR__.'/partials.php';
require_once __DIR__.'/mailer.php';
require_admin();

$msg = null;
$err = null;

function site_base_url(): string {
  $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme . '://' . $host;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'invite') {
      $first = trim($_POST['first_name'] ?? '');
      $last  = trim($_POST['last_name'] ?? '');
      $email = strtolower(trim($_POST['email'] ?? ''));
      $isAdmin = !empty($_POST['is_admin']) ? 1 : 0;

      if ($first === '' || $last === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Please provide first name, last name, and a valid email.');
      }

      // Check if a user already exists
      $st = pdo()->prepare('SELECT * FROM users WHERE email = ?');
      $st->execute([$email]);
      $existing = $st->fetch();

      $token = bin2hex(random_bytes(32));
      $expires = date('Y-m-d H:i:s', time() + 7*24*3600); // 7 days

      if ($existing) {
        if (!empty($existing['email_verified_at'])) {
          throw new RuntimeException('A user with that email already exists.');
        }
        // Re-invite pending user
        $upd = pdo()->prepare('UPDATE users SET first_name=?, last_name=?, is_admin=?, invite_token=?, invite_expires_at=?, invited_by_user_id=? WHERE id=?');
        $upd->execute([$first, $last, $isAdmin, $token, $expires, (int)current_user()['id'], (int)$existing['id']]);
        $inviteUserId = (int)$existing['id'];
      } else {
        // Insert brand new pending user; set random password hash placeholder
        $randHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $ins = pdo()->prepare('INSERT INTO users (first_name,last_name,email,password_hash,is_admin,invite_token,invite_expires_at,invited_by_user_id) VALUES (?,?,?,?,?,?,?,?)');
        $ins->execute([$first, $last, $email, $randHash, $isAdmin, $token, $expires, (int)current_user()['id']]);
        $inviteUserId = (int)pdo()->lastInsertId();
      }

      // Send invitation email
      $base = site_base_url();
      $link = $base . '/accept_invite.php?token=' . urlencode($token);
      $siteTitle = get_setting('site_title', defined('APP_NAME') ? APP_NAME : 'LillyChat');
      $subject = 'You\'re invited to join ' . $siteTitle;
      $html = '<p>Hello '.htmlspecialchars($first.' '.$last).',</p>'
            . '<p>You have been invited to join '.$siteTitle.'. Click the button below to set your password and activate your account:</p>'
            . '<p><a href="'.htmlspecialchars($link).'" style="display:inline-block;background:#f66b0e;color:#1a0b06;padding:10px 14px;border-radius:8px;text-decoration:none;font-weight:600;">Accept Invitation</a></p>'
            . '<p>If the button doesn\'t work, copy and paste this link:<br>'.htmlspecialchars($link).'</p>';
      @send_email($email, $subject, $html, $first.' '.$last);

      $msg = 'Invitation sent to '.$email.'.';
    } elseif ($action === 'resend') {
      $id = (int)($_POST['id'] ?? 0);
      $st = pdo()->prepare('SELECT * FROM users WHERE id = ?');
      $st->execute([$id]);
      $u = $st->fetch();
      if (!$u) throw new RuntimeException('User not found.');
      if (!empty($u['email_verified_at'])) throw new RuntimeException('User is already active.');

      $token = bin2hex(random_bytes(32));
      $expires = date('Y-m-d H:i:s', time() + 7*24*3600);
      $upd = pdo()->prepare('UPDATE users SET invite_token=?, invite_expires_at=?, invited_by_user_id=? WHERE id=?');
      $upd->execute([$token, $expires, (int)current_user()['id'], $id]);

      $base = site_base_url();
      $link = $base . '/accept_invite.php?token=' . urlencode($token);
      $siteTitle = get_setting('site_title', defined('APP_NAME') ? APP_NAME : 'LillyChat');
      $subject = 'Your invitation to ' . $siteTitle;
      $html = '<p>Hello '.htmlspecialchars($u['first_name'].' '.$u['last_name']).',</p>'
            . '<p>Here is your invitation link to activate your account:</p>'
            . '<p><a href="'.htmlspecialchars($link).'" style="display:inline-block;background:#f66b0e;color:#1a0b06;padding:10px 14px;border-radius:8px;text-decoration:none;font-weight:600;">Accept Invitation</a></p>'
            . '<p>If the button doesn\'t work, copy and paste this link:<br>'.htmlspecialchars($link).'</p>';
      @send_email($u['email'], $subject, $html, $u['first_name'].' '.$u['last_name']);

      $msg = 'Invitation re-sent.';
    } elseif ($action === 'set_admin') {
      $id = (int)($_POST['id'] ?? 0);
      $val = !empty($_POST['is_admin']) ? 1 : 0;
      $me = current_user();
      if ($id === (int)$me['id'] && $val === 0) {
        throw new RuntimeException('You cannot remove your own admin access here.');
      }
      $upd = pdo()->prepare('UPDATE users SET is_admin = ? WHERE id = ?');
      $upd->execute([$val, $id]);
      $msg = 'Updated role.';
    } elseif ($action === 'update_user') {
      $id = (int)($_POST['id'] ?? 0);
      $first = trim($_POST['first_name'] ?? '');
      $last  = trim($_POST['last_name'] ?? '');
      $email = strtolower(trim($_POST['email'] ?? ''));
      $isAdmin = !empty($_POST['is_admin']) ? 1 : 0;
      $password = $_POST['password'] ?? '';

      if ($id <= 0 || $first === '' || $last === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Please provide first name, last name, and a valid email.');
      }
      $me = current_user();
      if ($id === (int)$me['id'] && $isAdmin === 0) {
        throw new RuntimeException('You cannot remove your own admin access here.');
      }

      try {
        if ($password !== '') {
          if (strlen($password) < 8) throw new RuntimeException('New password must be at least 8 characters.');
          $hash = password_hash($password, PASSWORD_DEFAULT);
          $st = pdo()->prepare('UPDATE users SET first_name=?, last_name=?, email=?, is_admin=?, password_hash=? WHERE id=?');
          $st->execute([$first, $last, $email, $isAdmin, $hash, $id]);
        } else {
          $st = pdo()->prepare('UPDATE users SET first_name=?, last_name=?, email=?, is_admin=? WHERE id=?');
          $st->execute([$first, $last, $email, $isAdmin, $id]);
        }
        $msg = 'User updated.';
      } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1062) {
          throw new RuntimeException('That email is already in use.');
        }
        throw $e;
      }
    } elseif ($action === 'delete_user') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new RuntimeException('Invalid user.');
      $me = current_user();
      if ($id === (int)$me['id']) throw new RuntimeException('You cannot delete your own account here.');
      try {
        $del = pdo()->prepare('DELETE FROM users WHERE id = ?');
        $del->execute([$id]);
        $msg = 'User deleted.';
      } catch (PDOException $e) {
        // Likely FK constraints: messages or created chats
        throw new RuntimeException('Cannot delete this user because they are referenced by chats or messages.');
      }
    } else {
      throw new RuntimeException('Unknown action.');
    }
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

// Fetch roster
$rows = pdo()->query("SELECT * FROM users ORDER BY last_name, first_name")->fetchAll();

header_html('Users');
?>
<h2>Users</h2>
<?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <h3>Invite a member</h3>
  <form method="post" class="stack">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="action" value="invite">
    <label>First name<input type="text" name="first_name" required></label>
    <label>Last name<input type="text" name="last_name" required></label>
    <label>Email<input type="email" name="email" required></label>
    <label class="inline"><input type="checkbox" name="is_admin" value="1"> Make admin</label>
    <button class="button">Invite</button>
  </form>
</div>

<div class="card" style="margin-top:14px;">
  <h3>Roster</h3>
  <table>
    <thead>
      <tr><th>Name</th><th>Email</th><th>Status</th><th>Admin</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): 
        $pending = (empty($r['email_verified_at']) && !empty($r['invite_token']));
        $isMe = (current_user()['id'] == $r['id']);
      ?>
      <tr>
        <td><?=h($r['first_name'].' '.$r['last_name'])?></td>
        <td><?=h($r['email'])?></td>
        <td>
          <?php if (!empty($r['email_verified_at'])): ?>
            Active
          <?php elseif ($pending): ?>
            Invited (pending)
          <?php else: ?>
            Inactive
          <?php endif; ?>
        </td>
        <td>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
            <input type="hidden" name="action" value="set_admin">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <label class="inline small">
              <input type="checkbox" name="is_admin" value="1" <?=!empty($r['is_admin'])?'checked':''?> <?= $isMe ? 'disabled' : '' ?>>
              Admin
            </label>
            <?php if (!$isMe): ?>
              <button class="button secondary" style="margin-left:6px;">Save</button>
            <?php endif; ?>
          </form>
        </td>
        <td>
          <?php if ($pending): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
              <input type="hidden" name="action" value="resend">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="button secondary">Re-send invite</button>
            </form>
          <?php else: ?>
            <span class="small" style="color:var(--muted);">—</span>
          <?php endif; ?>

          <details style="margin-top:6px;">
            <summary>Edit / Delete</summary>
            <form method="post" class="stack" style="margin-top:8px;">
              <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
              <input type="hidden" name="action" value="update_user">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <label>First name
                <input type="text" name="first_name" value="<?=h($r['first_name'])?>" required>
              </label>
              <label>Last name
                <input type="text" name="last_name" value="<?=h($r['last_name'])?>" required>
              </label>
              <label>Email
                <input type="email" name="email" value="<?=h($r['email'])?>" required>
              </label>
              <label class="inline small">
                <input type="checkbox" name="is_admin" value="1" <?=!empty($r['is_admin'])?'checked':''?> <?= (current_user()['id'] == $r['id']) ? 'disabled' : '' ?>>
                Admin
              </label>
              <label>Set new password (optional)
                <input type="password" name="password" minlength="8">
              </label>
              <div class="actions" style="display:flex; gap:8px;">
                <button class="button">Save</button>
                <form method="post" onsubmit="return confirm('Delete this user? This cannot be undone.');" style="display:inline">
                  <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="button danger" <?= (current_user()['id'] == $r['id']) ? 'disabled' : '' ?>>Delete</button>
                </form>
              </div>
            </form>
          </details>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php footer_html(); ?>
