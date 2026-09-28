<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>500 — Server Error</title>
<link rel="stylesheet" href="/assets/css/style.css?v=1">
</head>
<body class="error-body">
  <main class="error-card">
    <p class="error-code">500</p>
    <h1>Something went wrong</h1>
    <p><?= e($message ?? 'An unexpected error occurred. Our team has been notified. Please try again shortly.') ?></p>
    <p><a class="btn btn-primary" href="/">Return Home</a></p>
    <?php if ($wa = preg_replace('/\D+/', '', (string)@setting('whatsapp_number', ''))): ?>
      <p>Need urgent help? <a href="https://wa.me/<?= e($wa) ?>" rel="noopener" target="_blank">Chat on WhatsApp</a></p>
    <?php endif; ?>
  </main>
</body>
</html>
