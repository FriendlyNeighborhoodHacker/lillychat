<?php
require_once __DIR__.'/partials.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
require_csrf();

$u = current_user();
$chatId = (int)($_POST['chat_id'] ?? 0);
if ($chatId <= 0) { http_response_code(400); exit('Bad request'); }

// Load chat
$st = pdo()->prepare('SELECT id FROM chats WHERE id = ?');
$st->execute([$chatId]);
$chat = $st->fetch();
if (!$chat) { http_response_code(404); exit('Chat not found'); }

// Check permission: admin or owner
$canPurge = false;
if (!empty($u['is_admin'])) {
  $canPurge = true;
} else {
  $own = pdo()->prepare('SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ? AND is_owner = 1');
  $own->execute([$chatId, (int)$u['id']]);
  $canPurge = (bool)$own->fetch();
}

if (!$canPurge) { http_response_code(403); exit('Not authorized'); }

// Delete chat (cascade removes messages and memberships)
try {
  $del = pdo()->prepare('DELETE FROM chats WHERE id = ?');
  $del->execute([$chatId]);
  header('Location: /index.php'); exit;
} catch (Throwable $e) {
  http_response_code(500);
  exit('Failed to purge chat');
}
