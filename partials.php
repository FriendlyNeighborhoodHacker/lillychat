<?php
require_once __DIR__ . '/auth.php';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function header_html(string $title) {
  $u = current_user();
  $cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
  $L = function(string $path, string $label) use ($cur) {
    $active = ($cur === basename($path));
    $a = '<a href="'.h($path).'">'.h($label).'</a>';
    return $active ? '<strong>'.$a.'</strong>' : $a;
  };

  $siteTitle = get_setting('site_title', defined('APP_NAME') ? APP_NAME : 'LillyChat');
  $announcement = trim((string)get_setting('announcement', ''));

  $nav = '';
  if ($u) {
    $nav .= $L('/index.php','Home').' | ';
    $nav .= $L('/chat_create.php','Create Chat').' | ';
    if (!empty($u['is_admin'])) {
      $nav .= $L('/admin_users.php','Users').' | ';
      $nav .= $L('/admin_chats.php','Manage Chats').' | ';
      $nav .= $L('/admin_settings.php','Settings').' | ';
    }
    $nav .= $L('/account.php','My Profile').' | ';
    $nav .= $L('/change_password.php','Change Password').' | ';
    $nav .= $L('/logout.php','Log out');
  } else {
    $nav .= $L('/login.php','Login');
  }

  $cssVer = @filemtime(__DIR__.'/styles.css'); if (!$cssVer) $cssVer = date('Ymd');
  echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
  echo '<title>'.h($title).' - '.h($siteTitle).'</title>';
  echo '<link rel="stylesheet" href="/styles.css?v='.h($cssVer).'">';
  echo '</head><body>';
  echo '<header><h1><a href="/index.php">'.h($siteTitle).'</a></h1><nav>'.$nav.'</nav></header>';
  if ($announcement !== '') {
    echo '<div class="announcement">'.nl2br(h($announcement)).'</div>';
  }
  echo '<main>';
}

function footer_html() {
  $jsVer = @filemtime(__DIR__.'/main.js'); if (!$jsVer) $jsVer = date('Ymd');
  echo '</main><script src="/main.js?v='.h($jsVer).'"></script></body></html>';
}
