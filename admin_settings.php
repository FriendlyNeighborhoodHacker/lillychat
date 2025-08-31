<?php
require_once __DIR__.'/partials.php';
require_admin();

$msg = null;
$err = null;

$siteTitle = get_setting('site_title', defined('APP_NAME') ? APP_NAME : 'LillyChat');
$announcement = (string)get_setting('announcement', '');
$timeZone = (string)get_setting('time_zone', 'America/New_York');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  try {
    $siteTitle = trim($_POST['site_title'] ?? $siteTitle);
    $announcement = trim($_POST['announcement'] ?? $announcement);
    $timeZone = trim($_POST['time_zone'] ?? $timeZone);

    if ($siteTitle === '') throw new RuntimeException('Site title is required.');
    if ($timeZone === '') throw new RuntimeException('Time zone is required.');

    set_setting('site_title', $siteTitle);
    set_setting('announcement', $announcement);
    set_setting('time_zone', $timeZone);

    $msg = 'Settings saved.';
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

header_html('Settings');
?>
<h2>Application Settings</h2>
<?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <form method="post" class="stack">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <label>Site Title
      <input type="text" name="site_title" value="<?=h($siteTitle)?>" required>
    </label>
    <label>Time Zone
      <input type="text" name="time_zone" value="<?=h($timeZone)?>" required>
      <div class="small" style="color:var(--muted)">Example: America/New_York</div>
    </label>
    <label>Announcement (optional)
      <textarea name="announcement" placeholder="Announcement for all users (optional)"><?=h($announcement)?></textarea>
    </label>
    <div class="actions" style="display:flex; gap:8px;">
      <button class="button" type="submit">Save Settings</button>
      <a class="button secondary" href="/index.php">Cancel</a>
    </div>
  </form>
</div>
<?php footer_html(); ?>
