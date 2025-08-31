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
               u.id AS user_id, u.first_name, u.last_name, u.profile_photo
        FROM messages m
        JOIN users u ON u.id = m.user_id
        WHERE m.chat_id = ?
        ORDER BY m.created_at ASC, m.id ASC
      ");
      $st->execute([$selectedChatId]);
      $messages = $st->fetchAll();

      // Also fetch chat members for the Members modal (shown to members)
      $chatMembers = [];
      $st = pdo()->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, cm.is_owner
        FROM chat_members cm
        JOIN users u ON u.id = cm.user_id
        WHERE cm.chat_id = ?
        ORDER BY u.last_name, u.first_name
      ");
      $st->execute([$selectedChatId]);
      $chatMembers = $st->fetchAll();
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
            <button type="button" class="button secondary" id="open-members">Members</button>
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
              <?php
                $mine = ((int)($u['id']) === (int)($m['user_id'] ?? 0));
                $avatarUrl = !empty($m['profile_photo']) ? '/'.ltrim($m['profile_photo'], '/') : '';
                $initials = strtoupper(mb_substr($m['first_name'] ?? '', 0, 1) . mb_substr($m['last_name'] ?? '', 0, 1));
              ?>
              <div class="msg-row <?= $mine ? 'mine' : 'theirs' ?>">
                <?php if (!$mine): ?>
                  <div class="avatar small">
                    <?php if ($avatarUrl): ?>
                      <img src="<?=h($avatarUrl)?>" alt="">
                    <?php else: ?>
                      <span class="initials"><?=h($initials)?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <div class="message <?= $mine ? 'mine' : 'theirs' ?>">
                  <div class="meta">
                    <span class="author"><?=h($m['first_name'].' '.$m['last_name'])?></span>
                    <span class="time"><?=h($m['created_at'])?></span>
                  </div>
                  <div class="body"><?=nl2br(h($m['body']))?></div>
                </div>
                <?php if ($mine): ?>
                  <div class="avatar small">
                    <?php if ($avatarUrl): ?>
                      <img src="<?=h($avatarUrl)?>" alt="">
                    <?php else: ?>
                      <span class="initials"><?=h($initials)?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            <div id="last"></div>
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

<?php if (!empty($chat) && !empty($isMember) && !empty($chatMembers)): ?>
  <div id="members-modal" class="modal-overlay hidden" aria-hidden="true">
    <div class="modal-card">
      <div class="modal-header" style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
        <strong>Members (<?= count($chatMembers) ?>)</strong>
        <button type="button" class="button secondary close-modal" id="close-members">Close</button>
      </div>
      <div class="modal-body">
        <ul class="members-list" style="list-style:none; margin:0; padding:0;">
          <?php foreach ($chatMembers as $cm): ?>
            <li style="display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.06);">
              <div>
                <div style="font-weight:600;"><?=h($cm['first_name'].' '.$cm['last_name'])?></div>
                <div class="small" style="color:var(--muted)"><?=h($cm['email'])?></div>
              </div>
              <?php if (!empty($cm['is_owner'])): ?>
                <span class="badge" style="background:rgba(255,122,24,0.2); color:var(--accent-3); padding:4px 8px; border-radius:999px; border:1px solid rgba(255,255,255,0.08);">Owner</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
<?php endif; ?>
</div>
<?php footer_html(); ?>
