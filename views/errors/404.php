<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>404 — Page Not Found</title>
<link rel="stylesheet" href="/assets/css/style.css?v=1">
</head>
<body class="error-body">
  <main class="error-card">
    <p class="error-code">404</p>
    <h1>Page not found</h1>
    <p><?= e($message ?? 'The page you are looking for could not be found.') ?></p>
    <p><a class="btn btn-primary" href="/">Go to Home</a> &nbsp; <a class="btn btn-outline" href="/shop">Browse Shop</a></p>
    <form class="search-form" action="/search" method="get" role="search">
      <input type="search" name="q" placeholder="Search products…" aria-label="Search products">
      <button type="submit" aria-label="Search">🔍</button>
    </form>
  </main>
</body>
</html>
