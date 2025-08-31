<?php
require_once __DIR__.'/partials.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
require_csrf();

$u = current_user();
$chatId = (int)($_POST['chat_id'] ?? 0);
if ($chatId <= 0) { http_response_code(400); exit('Bad request'); }

// If not a member, nothing to do
$st = pdo()->prepare('SELECT is_owner FROM chat_members WHERE chat_id = ? AND user_id = ?');
$st->execute([$chatId, (int)$u['id']]);
$membership = $st->fetch();

if ($membership) {
  try {
    // Remove membership
    $del = pdo()->prepare('DELETE FROM chat_members WHERE chat_id = ? AND user_id = ?');
    $del->execute([$chatId, (int)$u['id']]);
  } catch (Throwable $e) {
    http_response_code(500); exit('Failed to leave chat');
  }
}

header('Location: /index.php?chat='.(int)$chatId);
exit;
