<?php
require_once __DIR__.'/partials.php';
require_login();

$u = current_user();
$selectedChatId = isset($_GET['chat']) ? (int)$_GET['chat'] : 0;

// Fetch all chats with a flag if current user is a member
$chats = [];
$st = pdo()->prepare("
  SELECT c.id, c.title, c.description,
         EXISTS(SELECT 1 FROM chat_members m WHERE m.chat_id = c.id AND m.user_id = ?) AS is_member
  FROM chats c
  ORDER BY c.updated_at DESC, c.created_at DESC
");
$st->execute([$u['id']]);
$chats = $st->fetchAll();

// If a chat is selected, load its details and messages
$chat = null;
$isMember = false;
$messages = [];
$canPurge = false;

if ($selectedChatId) {
  $st = pdo()->prepare("SELECT * FROM chats WHERE id = ?");
  $st->execute([$selectedChatId]);
  $chat = $st->fetch();

  if ($chat) {
    // membership
    $st = pdo()->prepare("SELECT is_owner FROM chat_members WHERE chat_id = ? AND user_id = ?");
    $st->execute([$selectedChatId, $u['id']]);
    $m = $st->fetch();
    $isMember = !!$m;
    $isOwner = $m ? (bool)$m['is_owner'] : false;

    // purge allowed for admins or owners
    $canPurge = $isOwner || !empty($u['is_admin']);

    // messages (only show if member)
    if ($isMember) {
      $st = pdo()->prepare("
        SELECT m.id, m.body, m.created_at,
               u.first_name, u.last_name
        FROM messages m
        JOIN users u ON u.id = m.user_id
        WHERE m.chat_id = ?
        ORDER BY m.created_at ASC, m.id ASC
      ");
      $st->execute([$selectedChatId]);
      $messages = $st->fetchAll();
    }
  }
}

header_html('Home');
?>
<div class="chat-layout">

  <aside class="sidebar">
    <div class="sidebar-header">
      <div style="font-weight:600;">Chats</div>
      <a class="button secondary" href="/chat_create.php">Create</a>
    </div>
    <div class="chat-list">
      <?php if (empty($chats)): ?>
        <div class="card">No chats yet. Create the first one.</div>
      <?php else: ?>
        <?php foreach ($chats as $c): ?>
          <div class="chat-item">
            <a class="title" href="/index.php?chat=<?=h((string)$c['id'])?>"><?=h($c['title'])?></a>
            <div>
              <?php if ($c['is_member']): ?>
                <span class="small" style="color:var(--muted);">Joined</span>
              <?php else: ?>
                <form method="post" action="/chat_join.php" style="display:inline;">
                  <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                  <input type="hidden" name="chat_id" value="<?=h((string)$c['id'])?>">
                  <button class="button secondary" type="submit">Join</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </aside>

  <section class="content">
    <?php if (!$chat): ?>
      <div class="content-header">
        <div>Welcome</div>
      </div>
      <div class="content-body">
        <div class="card">
          <h2>Welcome to LillyChat</h2>
          <p>Select a chat from the left, or create a new chat.</p>
        </div>
      </div>
    <?php else: ?>
      <div class="content-header">
        <div>
          <strong><?=h($chat['title'])?></strong>
        </div>
        <div style="display:flex; gap:8px;">
          <?php if ($isMember): ?>
            <form method="post" action="/chat_leave.php">
              <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
              <input type="hidden" name="chat_id" value="<?=h((string)$chat['id'])?>">
              <button class="button secondary" type="submit">Leave chat</button>
            </form>
          <?php endif; ?>
          <?php if ($canPurge): ?>
            <form method="post" action="/chat_purge.php" onsubmit="return confirm('Delete this chat and all messages? This cannot be undone.');">
              <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
              <input type="hidden" name="chat_id" value="<?=h((string)$chat['id'])?>">
              <button class="button danger" type="submit">Purge chat</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <div class="content-body">
        <?php if (!empty($chat['description'])): ?>
          <p class="small" style="color:var(--muted);"><?=nl2br(h($chat['description']))?></p>
          <hr>
        <?php endif; ?>

        <?php if (!$isMember): ?>
          <div class="card">
            <p>You are not a member of this chat.</p>
            <form method="post" action="/chat_join.php">
              <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
              <input type="hidden" name="chat_id" value="<?=h((string)$chat['id'])?>">
              <button class="button" type="submit">Join this chat</button>
            </form>
          </div>
        <?php else: ?>
          <?php if (empty($messages)): ?>
            <p class="small" style="color:var(--muted);">No messages yet.</p>
          <?php else: ?>
            <?php foreach ($messages as $m): ?>
              <div class="message">
                <div class="meta">
                  <span class="author"><?=h($m['first_name'].' '.$m['last_name'])?></span>
                  <span class="time"><?=h($m['created_at'])?></span>
                </div>
              </div>
              <div style="margin: -8px 0 8px 0; padding-left: 8px;"><?=nl2br(h($m['body']))?></div>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <div class="content-footer">
        <?php if ($isMember): ?>
          <form method="post" class="compose" action="/message_send.php">
            <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
            <input type="hidden" name="chat_id" value="<?=h((string)$chat['id'])?>">
            <label class="small" style="flex:1;">
              <textarea name="body" placeholder="Write a message..." required></textarea>
            </label>
            <button type="submit">Send</button>
          </form>
        <?php else: ?>
          <div class="small" style="color:var(--muted);">Join the chat to send messages.</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

</div>
<?php footer_html(); ?>
