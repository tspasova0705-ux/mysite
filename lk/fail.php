<?php
require __DIR__ . '/inc/bootstrap.php';
// Return page after a failed or cancelled payment (GetPlatinum "fail URL").
$user = current_user();
$payUrl = setting('pay_url');
layout_head('Оплата не прошла', 'home', !$user); ?>
<div class="oops reveal">
  <div class="oops-face" aria-hidden="true">:(</div>
  <h1>Упс, что-то сегодня<br><span>не получилось</span></h1>
  <p>Оплата не прошла. Деньги не списаны, а если банк их заблокировал — он вернёт их автоматически. Попробуй ещё раз: можно выбрать другую карту или оформить рассрочку — сегодня 0 ₽.</p>
  <div class="row" style="justify-content:center">
    <?php if ($payUrl): ?><a class="btn btn-lime pulse" href="<?= e($payUrl) ?>">Попробовать ещё раз</a><?php endif; ?>
    <a class="btn btn-line" style="color:#fff" href="<?= url($user ? 'index.php' : '../index.html') ?>"><?= $user ? 'В личный кабинет' : 'На главную' ?></a>
  </div>
  <p class="small" style="opacity:.75;margin-top:22px">Не получается — напиши в канал: <?php foreach (CHANNELS as $label => $href): ?><a href="<?= e($href) ?>" target="_blank" rel="noopener" style="color:var(--lime)"><?= e($label) ?></a> <?php endforeach; ?></p>
</div>
<?php layout_foot();
