<?php
require __DIR__ . '/inc/bootstrap.php';
// Return page after a successful payment (GetPlatinum "success URL").
// The visit itself proves nothing, so access is still opened by the admin after checking the payment.
$user = current_user();
$paid = one('SELECT * FROM modules WHERE is_free=0 ORDER BY position, id LIMIT 1');
$created = false;
if ($user && $user['role'] === 'student' && $paid && !has_module_access($user, $paid)
    && !val("SELECT 1 FROM payment_requests WHERE user_id=? AND module_id=? AND status='new'", [$user['id'], $paid['id']])) {
    q("INSERT INTO payment_requests(user_id,module_id,note,status,created_at) VALUES(?,?,?,'new',?)",
        [$user['id'], $paid['id'], 'Вернулся со страницы успешной оплаты', now()]);
    $created = true;
}
$hasAccess = $user && $paid && has_module_access($user, $paid);

layout_head('Спасибо за оплату', 'home', !$user); ?>
<div class="paywall" style="margin-top:20px">
  <div class="card blue reveal">
    <span class="pill" style="border-color:rgba(255,255,255,.5);color:#fff">Оплата прошла</span>
    <h1 style="margin-top:22px">Спасибо<br>за <span style="color:var(--lime)">оплату!</span></h1>
    <?php if ($hasAccess): ?>
      <p style="margin:16px 0 26px;opacity:.85">Доступ к полному курсу уже открыт.</p>
      <a class="btn btn-lime" href="<?= url('index.php') ?>">К урокам →</a>
    <?php elseif ($user): ?>
      <p style="margin:16px 0 26px;opacity:.85">Мы получили отметку об оплате. Как только администратор сверит платёж, полный курс откроется в вашем кабинете — обычно в течение рабочего дня.</p>
      <a class="btn btn-lime" href="<?= url('index.php') ?>">В личный кабинет →</a>
    <?php else: ?>
      <p style="margin:16px 0 26px;opacity:.85">Остался один шаг: войдите в личный кабинет или зарегистрируйтесь <b>с той же почтой, что указали при оплате</b>, и нажмите «Я оплатил(а)». После проверки платежа откроем полный курс.</p>
      <div class="row">
        <a class="btn btn-lime" href="<?= url('register.php?buy=1') ?>">Зарегистрироваться</a>
        <a class="btn btn-line" style="color:#fff" href="<?= url('login.php?buy=1') ?>">Войти</a>
      </div>
    <?php endif; ?>
  </div>
  <div class="card reveal">
    <h2>Что дальше</h2>
    <ul class="list" style="margin-top:18px">
      <li>Доступ к курсу — 12 месяцев с момента открытия</li>
      <li>Домашние задания проверяются по пунктам</li>
      <li>После всех заданий и теста — сертификат</li>
    </ul>
    <p class="small muted mt">Вопросы — в канале: <?php foreach (CHANNELS as $label => $href): ?><a href="<?= e($href) ?>" target="_blank" rel="noopener"><?= e($label) ?></a> <?php endforeach; ?></p>
  </div>
</div>
<?php layout_foot();
