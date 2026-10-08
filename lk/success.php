<?php
require __DIR__ . '/inc/bootstrap.php';
// Return page after payment (GetPlatinum "success URL").
// With the secret key from Admin → Settings the course opens automatically;
// without it the student only leaves a request for the admin to check.
$user = current_user();
$keyOk = hash_equals(success_key(), (string)($_GET['k'] ?? ''));

if ($keyOk && $user && $user['role'] === 'student') {
    if (!has_full_access($user)) apply_payment((int)$user['id']);
    else $_SESSION['celebrate'] = 1;
    redirect('index.php');
}
if ($keyOk && !$user) $_SESSION['paid_pending'] = time();

// Fallback (no key): leave a request so the admin can verify and open access manually.
$paid = first_paid_module();
if (!$keyOk && $user && $user['role'] === 'student' && $paid && !has_module_access($user, $paid)
    && !val("SELECT 1 FROM payment_requests WHERE user_id=? AND module_id=? AND status='new'", [$user['id'], $paid['id']])) {
    q("INSERT INTO payment_requests(user_id,module_id,note,status,created_at) VALUES(?,?,?,'new',?)",
        [$user['id'], $paid['id'], 'Вернулся со страницы оплаты', now()]);
}

layout_head('Спасибо за оплату', 'home', !$user); ?>
<div class="paywall" style="margin-top:20px">
  <div class="card blue reveal">
    <span class="pill" style="border-color:rgba(255,255,255,.5);color:#fff">Оплата прошла</span>
    <h1 style="margin-top:22px">Спасибо<br>за <span style="color:var(--lime)">оплату!</span></h1>
    <?php if (!$user): ?>
      <p style="margin:16px 0 26px;opacity:.85">Остался один шаг: войди в личный кабинет или зарегистрируйся — <?= $keyOk ? 'полный курс откроется автоматически.' : 'и мы откроем полный курс после проверки платежа.' ?></p>
      <div class="row">
        <a class="btn btn-lime" href="<?= url('login.php') ?>">Войти</a>
        <a class="btn btn-line" style="color:#fff" href="<?= url('register.php') ?>">Зарегистрироваться</a>
      </div>
    <?php else: ?>
      <p style="margin:16px 0 26px;opacity:.85">Мы получили отметку об оплате. Как только администратор сверит платёж, полный курс откроется в кабинете — обычно в течение рабочего дня.</p>
      <a class="btn btn-lime" href="<?= url('index.php') ?>">В личный кабинет →</a>
    <?php endif; ?>
  </div>
  <div class="card reveal">
    <h2>Что дальше</h2>
    <ul class="list" style="margin-top:18px">
      <li>Доступ к курсу — 12 месяцев</li>
      <li>Домашние задания проверяются по пунктам</li>
      <li>После всех заданий и теста — сертификат</li>
    </ul>
    <p class="small muted mt">Вопросы — в канале: <?php foreach (CHANNELS as $label => $href): ?><a href="<?= e($href) ?>" target="_blank" rel="noopener"><?= e($label) ?></a> <?php endforeach; ?></p>
  </div>
</div>
<?php layout_foot();
