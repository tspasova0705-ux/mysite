<?php
require __DIR__ . '/inc/bootstrap.php';
// Return page after a failed or cancelled payment (GetPlatinum "fail URL").
$user = current_user();
$payUrl = setting('pay_url');
layout_head('Оплата не прошла', 'home', !$user); ?>
<div class="card reveal" style="margin-top:20px;text-align:center;padding:60px 24px;border-radius:36px">
  <span class="chip chip-redo">Оплата не завершена</span>
  <h1 style="margin:20px 0 14px">Платёж не прошёл</h1>
  <p class="muted" style="max-width:520px;margin:0 auto 28px">Деньги не списаны или будут возвращены банком автоматически. Попробуйте ещё раз — можно выбрать другую карту или оформить рассрочку на 6 или 12 месяцев.</p>
  <div class="row" style="justify-content:center">
    <?php if ($payUrl): ?><a class="btn btn-blue" href="<?= e($payUrl) ?>">Попробовать снова</a><?php endif; ?>
    <a class="btn btn-line" href="<?= url($user ? 'index.php' : '../index.html') ?>"><?= $user ? 'В личный кабинет' : 'На главную' ?></a>
  </div>
  <p class="small muted mt">Если не получается — напишите в канал: <?php foreach (CHANNELS as $label => $href): ?><a href="<?= e($href) ?>" target="_blank" rel="noopener"><?= e($label) ?></a> <?php endforeach; ?></p>
</div>
<?php layout_foot();
