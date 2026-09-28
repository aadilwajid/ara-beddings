<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php require dirname(__DIR__, 2) . '/views/partials/header.php'; ?>

<div class="auth-card">
  <h1>Login</h1>
  <form method="post" action="/login<?= !empty($_GET['next']) ? '?next=' . urlencode((string)$_GET['next']) : '' ?>" autocomplete="on">
    <?= csrf_field() ?>
    <label>Email *<input type="email" name="email" required maxlength="190" autocomplete="email"></label>
    <label>Password *<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-primary btn-block" type="submit">Login</button>
  </form>
  <p class="auth-alt"><a href="/forgot-password">Forgot password?</a></p>
  <p class="auth-alt">New here? <a href="/register">Create an account</a> — or continue as guest at checkout.</p>
</div>

<?php require dirname(__DIR__, 2) . '/views/partials/footer.php'; ?>
