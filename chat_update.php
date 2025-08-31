<?php
require_once __DIR__.'/partials.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
require_csrf();

$u = current_user();
$chatId = (int)($_POST['chat_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$desc  = trim($_POST['description'] ?? '');

if ($chatId <= 0) { http_response_code(400); exit('Bad request'); }
if ($title === '' || mb_strlen($title) > 255) { http_response_code(400); exit('Title required and must be <= 255 chars'); }

// Load chat
$st = pdo()->prepare('SELECT id, created_by_user_id FROM chats WHERE id = ?');
$st->execute([$chatId]);
$chat = $st->fetch();
if (!$chat) { http_response_code(404); exit('Chat not found'); }

// Permission: admin or chat creator
$canEdit = !empty($u['is_admin']) || ((int)$chat['created_by_user_id'] === (int)$u['id']);
if (!$canEdit) { http_response_code(403); exit('Not authorized'); }

// Update
try {
  $st = pdo()->prepare('UPDATE chats SET title = ?, description = ? WHERE id = ?');
  $st->execute([$title, ($desc !== '' ? $desc : null), $chatId]);
  header('Location: /index.php?chat='.$chatId);
  exit;
} catch (Throwable $e) {
  http_response_code(500);
  exit('Failed to update chat');
}
