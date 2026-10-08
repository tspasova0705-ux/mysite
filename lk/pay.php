<?php
require __DIR__ . '/inc/bootstrap.php';
$user = require_login();

$m = one('SELECT * FROM modules WHERE id=?', [get_int('m')]);
if (!$m) redirect('index.php');
if (has_module_access($user, $m)) redirect('index.php');

$payUrl = $m['pay_url'] ?: setting('pay_url');
$request = one("SELECT * FROM payment_requests WHERE user_id=? AND module_id=? AND status='new'", [$user['id'], $m['id']]);

if (is_post()) {
    csrf_check();
    if (!$request) {
        q("INSERT INTO payment_requests(user_id,module_id,note,status,created_at) VALUES(?,?,?,'new',?)", [$user['id'], $m['id'], mb_substr(post('note'), 0, 500), now()]);
        flash('Спасибо! Администратор проверит оплату и откроет доступ к модулю.');
    }
    redirect('pay.php?m=' . $m['id']);
}

$lessons = module_lessons((int)$m['id']);
$paidMods = all('SELECT * FROM modules WHERE is_free=0 ORDER BY position, id');
layout_head('Оплата модуля', 'home'); ?>

<div class="crumbs"><a href="<?= url('index.php') ?>">Мои уроки</a><span>/</span><span>Открыть модуль</span></div>

<div class="paywall">
  <div class="card blue reveal">
    <span class="pill" style="border-color:rgba(255,255,255,.5);color:#fff">Платный модуль</span>
    <h1 style="margin-top:22px"><?= e($m['title']) ?></h1>
    <p style="margin-top:12px;opacity:.85"><?= e($m['subtitle']) ?></p>
    <div class="price"><?= e($m['price_label'] ?: 'Цена уточняется') ?></div>
    <?php if ($request): ?>
      <div class="flash flash-ok" style="color:var(--ink)">Заявка отправлена <?= e(date('d.m.Y H:i', strtotime($request['created_at']))) ?>. Доступ откроется после проверки оплаты.</div>
    <?php else: ?>
      <div class="row">
        <?php if ($payUrl): ?><a class="btn btn-lime" href="<?= e($payUrl) ?>" target="_blank" rel="noopener">Оплатить</a><?php endif; ?>
      </div>
      <form method="post" class="mt" style="max-width:480px">
        <?= csrf_field() ?>
        <p class="small" style="opacity:.85;margin-bottom:10px"><?= e(setting('pay_text')) ?></p>
        <label class="field" style="color:#fff">Комментарий к оплате (необязательно)<input type="text" name="note" maxlength="500" placeholder="Например: оплатила 12.10 с карты на имя…"></label>
        <button class="btn btn-line" style="color:#fff" type="submit">Я оплатил(а)</button>
      </form>
    <?php endif; ?>
  </div>
  <div class="card reveal">
    <h2>Что внутри</h2>
    <ul class="list mt" style="margin-top:18px">
      <?php foreach ($lessons as $l): ?><li><?= e($l['title']) ?></li><?php endforeach; ?>
    </ul>
    <p class="small muted mt">В каждом уроке — видео, материалы и домашнее задание с проверкой.</p>
  </div>
</div>

<?php layout_foot();
