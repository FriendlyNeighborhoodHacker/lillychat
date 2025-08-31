<?php
require_once __DIR__.'/partials.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
require_csrf();

$u = current_user();
$chatId = (int)($_POST['chat_id'] ?? 0);
$body   = trim($_POST['body'] ?? '');

if ($chatId <= 0 || $body === '') { http_response_code(400); exit('Bad request'); }

// Ensure membership
$st = pdo()->prepare('SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?');
$st->execute([$chatId, (int)$u['id']]);
if (!$st->fetch()) { http_response_code(403); exit('Not a member'); }

// Insert message and bump chat updated_at
try {
  $ins = pdo()->prepare('INSERT INTO messages (chat_id, user_id, body) VALUES (?, ?, ?)');
  $ins->execute([$chatId, (int)$u['id'], $body]);

  $upd = pdo()->prepare('UPDATE chats SET updated_at = NOW() WHERE id = ?');
  $upd->execute([$chatId]);

  header('Location: /index.php?chat='.(int)$chatId.'#last'); exit;
} catch (Throwable $e) {
  http_response_code(500);
  exit('Failed to send message');
}
