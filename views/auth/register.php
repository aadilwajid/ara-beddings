<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php require dirname(__DIR__, 2) . '/views/partials/header.php'; ?>

<div class="auth-card">
  <h1>Create Account</h1>
  <form method="post" action="/register" autocomplete="on">
    <?= csrf_field() ?>
    <label>Full Name *<input type="text" name="name" required maxlength="120" autocomplete="name" value="<?= e(old('name')) ?>"></label>
    <label>Mobile Number *<input type="tel" name="phone" required maxlength="20" placeholder="03XXXXXXXXX" pattern="(?:\+?92|0)3\d{9}" title="Pakistani mobile, e.g. 03001234567" value="<?= e(old('phone')) ?>"></label>
    <label>Email *<input type="email" name="email" required maxlength="190" autocomplete="email" value="<?= e(old('email')) ?>"></label>
    <label>Password *<input type="password" name="password" required minlength="8" autocomplete="new-password">
      <small>At least 8 characters.</small></label>
    <label>Confirm Password *<input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
    <button class="btn btn-primary btn-block" type="submit">Register</button>
  </form>
  <p class="auth-alt">Already registered? <a href="/login">Login</a></p>
</div>

<?php require dirname(__DIR__, 2) . '/views/partials/footer.php'; ?>
