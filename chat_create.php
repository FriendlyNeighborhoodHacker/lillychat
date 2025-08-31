<?php
require_once __DIR__.'/partials.php';
require_login();

$u = current_user();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $title = trim($_POST['title'] ?? '');
  $desc  = trim($_POST['description'] ?? '');

  if ($title === '') {
    $error = 'Chat title is required.';
  } elseif (mb_strlen($title) > 255) {
    $error = 'Chat title is too long.';
  } else {
    try {
      $st = pdo()->prepare('INSERT INTO chats (title, description, created_by_user_id) VALUES (?, ?, ?)');
      $st->execute([$title, $desc !== '' ? $desc : null, (int)$u['id']]);
      $chatId = (int)pdo()->lastInsertId();

      // Add creator as owner
      $cm = pdo()->prepare('INSERT INTO chat_members (chat_id, user_id, is_owner) VALUES (?, ?, 1)');
      $cm->execute([$chatId, (int)$u['id']]);

      header('Location: /index.php?chat=' . $chatId); exit;
    } catch (Throwable $e) {
      $error = 'Failed to create chat.';
    }
  }
}

header_html('Create Chat');
?>
<h2>Create Chat</h2>
<?php if ($error): ?><p class="error"><?=h($error)?></p><?php endif; ?>

<div class="card">
  <form method="post" class="stack">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <label>Chat Title
      <input type="text" name="title" maxlength="255" required>
    </label>
    <label>Chat Description
      <textarea name="description" placeholder="What is this chat for? (optional)"></textarea>
    </label>
    <div style="display:flex; gap:8px;">
      <button type="submit">Create</button>
      <a class="button secondary" href="/index.php">Cancel</a>
    </div>
  </form>
</div>
<?php footer_html(); ?>
