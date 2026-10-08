<?php
require __DIR__ . '/inc/bootstrap.php';
$user = require_login();

$m = one('SELECT * FROM modules WHERE id=?', [get_int('m')]);
if (!$m) redirect('index.php');
if (has_module_access($user, $m)) redirect('index.php');

$full = sold_with_course($m);
$payUrl = $m['pay_url'] ?: setting('pay_url');
$request = one("SELECT * FROM payment_requests WHERE user_id=? AND module_id=? AND status='new'", [$user['id'], $m['id']]);

if (is_post()) {
    csrf_check();
    if (!$request) {
        q("INSERT INTO payment_requests(user_id,module_id,note,status,created_at) VALUES(?,?,?,'new',?)", [$user['id'], $m['id'], mb_substr(post('note'), 0, 500), now()]);
        flash('Спасибо! Администратор проверит оплату и откроет доступ.');
    }
    redirect('pay.php?m=' . $m['id']);
}

// What the purchase opens: the whole course, or just this module when it is sold separately.
$included = $full ? array_values(array_filter(modules(), 'sold_with_course')) : [$m];
$lessonCount = 0;
foreach ($included as $im) $lessonCount += count(module_lessons((int)$im['id']));
$legal = e(BASE) . '/../';

layout_head('Оплата', 'home'); ?>

<div class="crumbs"><a href="<?= url('index.php') ?>">Мои уроки</a><span>/</span><span>Оплата</span></div>

<div class="paywall">
  <div class="card blue reveal">
    <span class="pill" style="border-color:rgba(255,255,255,.5);color:#fff"><?= $full ? 'Полный курс' : 'Платный модуль' ?></span>
    <h1 style="margin-top:22px"><?= $full ? e(COURSE_NAME) : e($m['title']) ?></h1>
    <p style="margin-top:12px;opacity:.85"><?= $full ? count($included) . ' ' . plural(count($included), 'модуль', 'модуля', 'модулей') . ', ' . $lessonCount . ' ' . plural($lessonCount, 'урок', 'урока', 'уроков') . ' с проверкой домашних заданий, итоговый тест и сертификат' : e($m['subtitle']) ?></p>
    <div class="price"><?= e(price_of($m)) ?></div>
    <?php if ($full && setting('price_note')): ?><span class="chip chip-free" style="font-size:13px;padding:9px 14px;margin:-8px 0 14px"><?= e(setting('price_note')) ?></span>
      <p class="small" style="opacity:.8;margin-bottom:22px">Рассрочка на 6 или 12 месяцев: <?= e(installment_hint()) ?> Точный график платежей покажет банк при оформлении.</p><?php endif; ?>
    <?php if ($request): ?>
      <div class="flash flash-ok" style="color:var(--ink)">Заявка отправлена <?= e(date('d.m.Y H:i', strtotime($request['created_at']))) ?>. Доступ откроется после проверки оплаты.</div>
    <?php else: ?>
      <div class="row">
        <?php if ($payUrl): ?><a class="btn btn-lime pulse" href="<?= e($payUrl) ?>" target="_blank" rel="noopener">Перейти к оплате</a>
        <?php else: ?><span class="small" style="opacity:.85">Ссылка на оплату скоро появится.</span><?php endif; ?>
      </div>
      <p class="small" style="opacity:.75;margin-top:12px">Нажимая «Оплатить», вы принимаете условия <a href="<?= $legal ?>offer.html" target="_blank" style="color:var(--lime)">публичной оферты</a>.</p>
      <form method="post" class="mt" style="max-width:480px">
        <?= csrf_field() ?>
        <p class="small" style="opacity:.85;margin-bottom:10px"><?= e(setting('pay_text')) ?></p>
        <label class="field" style="color:#fff">Комментарий к оплате (необязательно)<input type="text" name="note" maxlength="500" placeholder="Например: оформила рассрочку 12.10"></label>
        <button class="btn btn-line" style="color:#fff" type="submit">Я оплатил(а)</button>
      </form>
    <?php endif; ?>
  </div>
  <div class="card reveal">
    <h2>Что откроется</h2>
    <ul class="list mt" style="margin-top:18px">
      <?php foreach ($included as $im): ?><li><?= e($im['title']) ?> · <?= count(module_lessons((int)$im['id'])) ?> ур.</li><?php endforeach; ?>
    </ul>
    <?php if ($full): ?>
      <h3 class="mt">Бонусы</h3>
      <ul class="list" style="margin-top:12px">
        <li>Шаблон «Паспорт оффера»</li><li>Таблица аналитики</li><li>Контент-план на 30 дней</li><li>Чек-лист первого теста</li>
      </ul>
    <?php endif; ?>
    <p class="small muted mt">В каждом уроке — видео, материалы и домашнее задание с проверкой по пунктам.</p>
  </div>
</div>

<?php layout_foot();
