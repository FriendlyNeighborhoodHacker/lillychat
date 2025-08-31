<?php
require_once __DIR__.'/partials.php';
require_admin();

$msg = null;
$err = null;

function fetch_chat(int $chatId) {
  $st = pdo()->prepare('SELECT * FROM chats WHERE id = ?');
  $st->execute([$chatId]);
  return $st->fetch();
}

function fetch_members(int $chatId) {
  $st = pdo()->prepare('
    SELECT u.id as user_id, u.first_name, u.last_name, u.email, cm.is_owner, cm.joined_at
    FROM chat_members cm
    JOIN users u ON u.id = cm.user_id
    WHERE cm.chat_id = ?
    ORDER BY u.last_name, u.first_name
  ');
  $st->execute([$chatId]);
  return $st->fetchAll();
}

$chatId = isset($_GET['chat']) ? (int)$_GET['chat'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'update_chat') {
      $chatId = (int)($_POST['chat_id'] ?? 0);
      $title = trim($_POST['title'] ?? '');
      $desc  = trim($_POST['description'] ?? '');
      if ($chatId <= 0) throw new RuntimeException('Invalid chat.');
      if ($title === '' || mb_strlen($title) > 255) throw new RuntimeException('Title is required and must be at most 255 characters.');
      $st = pdo()->prepare('UPDATE chats SET title = ?, description = ? WHERE id = ?');
      $st->execute([$title, $desc !== '' ? $desc : null, $chatId]);
      $msg = 'Chat updated.';
    } elseif ($action === 'delete_chat') {
      $chatId = (int)($_POST['chat_id'] ?? 0);
      if ($chatId <= 0) throw new RuntimeException('Invalid chat.');
      $st = pdo()->prepare('DELETE FROM chats WHERE id = ?');
      $st->execute([$chatId]);
      header('Location: /admin_chats.php'); exit;
    } elseif ($action === 'add_member') {
      $chatId = (int)($_POST['chat_id'] ?? 0);
      $email  = strtolower(trim($_POST['email'] ?? ''));
      $isOwner = !empty($_POST['is_owner']) ? 1 : 0;
      if ($chatId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Provide a valid email.');
      // Find user
      $st = pdo()->prepare('SELECT id, first_name, last_name FROM users WHERE email = ?');
      $st->execute([$email]);
      $u = $st->fetch();
      if (!$u) throw new RuntimeException('No user found with that email.');
      // Insert membership if not exists
      $chk = pdo()->prepare('SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?');
      $chk->execute([$chatId, (int)$u['id']]);
      if (!$chk->fetch()) {
        $ins = pdo()->prepare('INSERT INTO chat_members (chat_id, user_id, is_owner) VALUES (?, ?, ?)');
        $ins->execute([$chatId, (int)$u['id'], $isOwner]);
        $msg = 'Added '.h($u['first_name'].' '.$u['last_name']).' to chat.';
      } else {
        // Update owner flag if requested
        if ($isOwner) {
          $upd = pdo()->prepare('UPDATE chat_members SET is_owner = 1 WHERE chat_id = ? AND user_id = ?');
          $upd->execute([$chatId, (int)$u['id']]);
        }
        $msg = 'User is already a member.'.($isOwner ? ' Ownership updated.' : '');
      }
    } elseif ($action === 'remove_member') {
      $chatId = (int)($_POST['chat_id'] ?? 0);
      $userId = (int)($_POST['user_id'] ?? 0);
      if ($chatId <= 0 || $userId <= 0) throw new RuntimeException('Invalid request.');
      $del = pdo()->prepare('DELETE FROM chat_members WHERE chat_id = ? AND user_id = ?');
      $del->execute([$chatId, $userId]);
      $msg = 'Member removed.';
    } elseif ($action === 'make_owner' || $action === 'remove_owner') {
      $chatId = (int)($_POST['chat_id'] ?? 0);
      $userId = (int)($_POST['user_id'] ?? 0);
      if ($chatId <= 0 || $userId <= 0) throw new RuntimeException('Invalid request.');
      $val = ($action === 'make_owner') ? 1 : 0;
      $upd = pdo()->prepare('UPDATE chat_members SET is_owner = ? WHERE chat_id = ? AND user_id = ?');
      $upd->execute([$val, $chatId, $userId]);
      $msg = $val ? 'Member set as owner.' : 'Owner flag removed.';
    } else {
      throw new RuntimeException('Unknown action.');
    }
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

// Data for rendering
$chat = null;
$members = [];
if ($chatId) {
  $chat = fetch_chat($chatId);
  if ($chat) $members = fetch_members($chatId);
}

header_html('Manage Chats');
?>
<h2>Manage Chats</h2>
<?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<?php if (!$chat): ?>
  <div class="card">
    <h3>All Chats</h3>
    <table>
      <thead>
        <tr><th>Title</th><th>Members</th><th>Messages</th><th>Created</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php
        $rows = pdo()->query('
          SELECT c.*,
            (SELECT COUNT(*) FROM chat_members m WHERE m.chat_id = c.id) AS member_count,
            (SELECT COUNT(*) FROM messages mm WHERE mm.chat_id = c.id) AS message_count
          FROM chats c
          ORDER BY c.updated_at DESC, c.created_at DESC
        ')->fetchAll();
        if (empty($rows)): ?>
          <tr><td colspan="5" class="small" style="color:var(--muted);">No chats found.</td></tr>
        <?php else:
          foreach ($rows as $r): ?>
          <tr>
            <td><?=h($r['title'])?></td>
            <td><?= (int)$r['member_count'] ?></td>
            <td><?= (int)$r['message_count'] ?></td>
            <td><?=h($r['created_at'])?></td>
            <td style="white-space:nowrap;">
              <a class="button secondary" href="/admin_chats.php?chat=<?= (int)$r['id'] ?>">Manage</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this chat and all messages?');">
                <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                <input type="hidden" name="action" value="delete_chat">
                <input type="hidden" name="chat_id" value="<?= (int)$r['id'] ?>">
                <button class="button danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="card">
    <h3>Edit Chat</h3>
    <form method="post" class="stack">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <input type="hidden" name="action" value="update_chat">
      <input type="hidden" name="chat_id" value="<?= (int)$chat['id'] ?>">
      <label>Title
        <input type="text" name="title" value="<?=h($chat['title'])?>" maxlength="255" required>
      </label>
      <label>Description
        <textarea name="description" placeholder="Optional"><?=h($chat['description'])?></textarea>
      </label>
      <div style="display:flex; gap:8px; align-items:center;">
        <button class="button">Save</button>
        <a class="button secondary" href="/admin_chats.php">Back</a>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this chat and all messages?');">
          <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
          <input type="hidden" name="action" value="delete_chat">
          <input type="hidden" name="chat_id" value="<?= (int)$chat['id'] ?>">
          <button class="button danger" type="submit">Delete Chat</button>
        </form>
      </div>
    </form>
  </div>

  <div class="card" style="margin-top:14px;">
    <h3>Members</h3>
    <form method="post" class="stack" style="margin-bottom:12px;">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <input type="hidden" name="action" value="add_member">
      <input type="hidden" name="chat_id" value="<?= (int)$chat['id'] ?>">
      <label>Add member by email
        <input type="email" name="email" placeholder="user@example.com" required>
      </label>
      <label class="inline small"><input type="checkbox" name="is_owner" value="1"> Make owner</label>
      <button class="button">Add Member</button>
    </form>

    <table>
      <thead>
        <tr><th>Name</th><th>Email</th><th>Owner</th><th>Joined</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($members)): ?>
          <tr><td colspan="5" class="small" style="color:var(--muted);">No members yet.</td></tr>
        <?php else:
          foreach ($members as $m): ?>
          <tr>
            <td><?=h($m['last_name'].', '.$m['first_name'])?></td>
            <td><?=h($m['email'])?></td>
            <td><?= !empty($m['is_owner']) ? 'Yes' : 'No' ?></td>
            <td><?=h($m['joined_at'])?></td>
            <td style="white-space:nowrap;">
              <?php if (empty($m['is_owner'])): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                  <input type="hidden" name="action" value="make_owner">
                  <input type="hidden" name="chat_id" value="<?= (int)$chat['id'] ?>">
                  <input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
                  <button class="button secondary">Make Owner</button>
                </form>
              <?php else: ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                  <input type="hidden" name="action" value="remove_owner">
                  <input type="hidden" name="chat_id" value="<?= (int)$chat['id'] ?>">
                  <input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
                  <button class="button secondary">Remove Owner</button>
                </form>
              <?php endif; ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Remove this member from the chat?');">
                <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                <input type="hidden" name="action" value="remove_member">
                <input type="hidden" name="chat_id" value="<?= (int)$chat['id'] ?>">
                <input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
                <button class="button danger">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php footer_html(); ?>
