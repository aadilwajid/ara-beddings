<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php require dirname(__DIR__, 2) . '/views/partials/header.php'; ?>

<h1 class="page-title"><?= e($heading) ?></h1>
<div class="policy-body">
  <?php if ($body): ?>
    <?= nl2br(e($body)) ?>
  <?php else: ?>
    <p>This policy has not been configured yet. Please <a href="/contact">contact us</a> for details.</p>
  <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/views/partials/footer.php'; ?>
