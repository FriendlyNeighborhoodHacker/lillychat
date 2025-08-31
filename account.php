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
      // Handle profile photo upload/remove
      $oldPhoto = $u['profile_photo'] ?? null;
      $newPhotoRel = null;

      // If remove requested, drop current photo
      $remove = !empty($_POST['remove_photo']);
      if ($remove && $oldPhoto) {
        $oldPath = __DIR__ . '/' . ltrim($oldPhoto, '/');
        if (is_file($oldPath) && strpos(realpath($oldPath) ?: '', realpath(__DIR__ . '/uploads/avatars') ?: '') === 0) {
          @unlink($oldPath);
        }
        $oldPhoto = null; // treat as removed
      }

      // If uploaded, validate and move to uploads/avatars
      if (!empty($_FILES['profile_photo']) && is_array($_FILES['profile_photo']) && (int)($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $tmp = $_FILES['profile_photo']['tmp_name'];
        $name = $_FILES['profile_photo']['name'] ?? 'upload';
        $size = (int)($_FILES['profile_photo']['size'] ?? 0);

        // Basic validations
        if ($size > 0 && $size <= 4 * 1024 * 1024) { // 4MB max
          $info = @getimagesize($tmp);
          if ($info && !empty($info['mime']) && strpos($info['mime'], 'image/') === 0) {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
              $ext = 'jpg';
            }
            $dir = __DIR__ . '/uploads/avatars';
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $base = 'avatar_' . (int)$u['id'] . '_' . time() . '.' . $ext;
            $dest = $dir . '/' . $base;

            if (@move_uploaded_file($tmp, $dest)) {
              // Optionally remove previous file if different
              if ($oldPhoto) {
                $oldPath = __DIR__ . '/' . ltrim($oldPhoto, '/');
                if (is_file($oldPath) && strpos(realpath($oldPath) ?: '', realpath(__DIR__ . '/uploads/avatars') ?: '') === 0) {
                  @unlink($oldPath);
                }
              }
              $newPhotoRel = 'uploads/avatars/' . $base; // relative path saved to DB
            }
          }
        }
      }

      // Build update query with optional photo change
      $fields = 'first_name = ?, last_name = ?, email = ?';
      $params = [$first, $last, $email];

      if ($remove) {
        $fields .= ', profile_photo = NULL';
      } elseif ($newPhotoRel) {
        $fields .= ', profile_photo = ?';
        $params[] = $newPhotoRel;
      }

      $params[] = (int)$u['id'];
      $sql = 'UPDATE users SET ' . $fields . ' WHERE id = ?';
      $st = pdo()->prepare($sql);
      $st->execute($params);

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
  <form method="post" class="stack" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <?php
      $avatarUrl = !empty($u['profile_photo']) ? '/'.ltrim($u['profile_photo'], '/') : '';
      $initials = initials($u['first_name'] ?? '', $u['last_name'] ?? '');
    ?>
    <div style="display:flex; align-items:center; gap:12px; margin: 6px 0 2px;">
      <div class="avatar large">
        <?php if ($avatarUrl): ?>
          <img src="<?=h($avatarUrl)?>" alt="">
        <?php else: ?>
          <span class="initials"><?=h($initials)?></span>
        <?php endif; ?>
      </div>
      <label class="small" style="margin:0;">
        Profile photo
        <input type="file" name="profile_photo" accept="image/*">
      </label>
    </div>
    <?php if ($avatarUrl): ?>
      <label class="inline small"><input type="checkbox" name="remove_photo" value="1"> Remove current photo</label>
    <?php endif; ?>
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
