<?php
require_once __DIR__.'/partials.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
require_csrf();

$u = current_user();
$chatId = (int)($_POST['chat_id'] ?? 0);
if ($chatId <= 0) { http_response_code(400); exit('Bad request'); }

// Ensure chat exists
$st = pdo()->prepare('SELECT id FROM chats WHERE id = ?');
$st->execute([$chatId]);
if (!$st->fetch()) { http_response_code(404); exit('Chat not found'); }

// Add membership if not exists
try {
  // Check if already a member
  $chk = pdo()->prepare('SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?');
  $chk->execute([$chatId, (int)$u['id']]);
  if (!$chk->fetch()) {
    $ins = pdo()->prepare('INSERT INTO chat_members (chat_id, user_id, is_owner) VALUES (?, ?, 0)');
    $ins->execute([$chatId, (int)$u['id']]);
  }
  header('Location: /index.php?chat='.(int)$chatId); exit;
} catch (Throwable $e) {
  http_response_code(500);
  exit('Failed to join chat');
}
