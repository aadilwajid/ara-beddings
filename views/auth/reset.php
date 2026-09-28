<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php require dirname(__DIR__, 2) . '/views/partials/header.php'; ?>

<div class="auth-card">
  <h1>Choose New Password</h1>
  <p>Hello <?= e($user['name']) ?>, set a new password for your account.</p>
  <form method="post" action="/reset-password">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <label>New Password *<input type="password" name="password" required minlength="8" autocomplete="new-password"><small>At least 8 characters.</small></label>
    <label>Confirm New Password *<input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
    <button class="btn btn-primary btn-block" type="submit">Update Password</button>
  </form>
</div>

<?php require dirname(__DIR__, 2) . '/views/partials/footer.php'; ?>
