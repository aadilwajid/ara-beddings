<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php require dirname(__DIR__, 2) . '/views/partials/header.php'; ?>

<div class="auth-card">
  <h1>Reset Password</h1>
  <p>Enter your email and we'll send you a reset link (if the email is registered).</p>
  <form method="post" action="/forgot-password">
    <?= csrf_field() ?>
    <label>Email *<input type="email" name="email" required maxlength="190" autocomplete="email"></label>
    <button class="btn btn-primary btn-block" type="submit">Send Reset Link</button>
  </form>
  <p class="auth-alt"><a href="/login">Back to login</a></p>
</div>

<?php require dirname(__DIR__, 2) . '/views/partials/footer.php'; ?>
