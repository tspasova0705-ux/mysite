<?php
require __DIR__ . '/inc/bootstrap.php';
$user = require_login();

[$done, $total, $pct] = progress($user);
$mods = modules();
$cert = one('SELECT * FROM certificates WHERE user_id=?', [$user['id']]);

// Next lesson to work on: the first one that is open or returned for rework.
$next = null;
$states = [];
foreach (course_lessons() as $l) {
    $states[$l['id']] = lesson_state($user, $l);
    if (!$next && in_array($states[$l['id']], ['open', 'returned'], true)) $next = $l;
}
$pending = count(array_filter($states, fn($s) => $s === 'submitted'));
$first = explode(' ', $user['name'])[0];

// Free module finished but the course is not bought yet -> invite to pay (popup once per session).
$upsell = $user['role'] === 'student' && free_completed($user) && !has_full_access($user);
$upsellPopup = $upsell && empty($_SESSION['upsell_shown']);
if ($upsellPopup) $_SESSION['upsell_shown'] = 1;
$payHref = setting('pay_url') ?: (($pm = first_paid_module()) ? url('pay.php?m=' . $pm['id']) : '#');
$celebrate = !empty($_SESSION['celebrate']);
unset($_SESSION['celebrate']);

layout_head('Мои уроки', 'home');

/** Invitation to buy the full course after the free lessons. */
function upsell_block(string $payHref, bool $inModal): void { ?>
  <div class="upsell<?= $inModal ? ' in-modal' : ' reveal' ?>">
    <div class="upsell-text">
      <span class="upsell-badge">🎉 Бесплатный модуль пройден</span>
      <h2>Поздравляем!</h2>
      <p>Теперь ты знаешь, что такое партнёрские программы и как с их помощью можно зарабатывать. Предлагаю разобраться подробнее — и уже <b>в первый месяц</b> сделать результат на партнёрском сервисе.</p>
    </div>
    <div class="upsell-pay">
      <div class="upsell-price"><?= e(setting('course_price', '19 990 ₽')) ?> <span>полный курс</span></div>
      <p>Для тебя есть возможность оплатить в рассрочку: <b>сегодня ты платишь 0 ₽</b> — и у тебя есть целый месяц, чтобы заняться обучением.</p>
      <a class="btn btn-lime pulse" href="<?= e($payHref) ?>" target="_blank" rel="noopener">Перейти к оплате →</a>
      <small><?= e(installment_hint()) ?> Рассрочка на 6 или 12 месяцев через GetPlatinum, точный график платежей покажет банк при оформлении.</small>
    </div>
  </div>
<?php }
?>
<?php if ($celebrate): ?>
<div class="modal open" role="dialog" aria-modal="true" aria-label="Оплата прошла">
  <div class="modal-box celebrate">
    <div class="confetti" aria-hidden="true"><?php for ($i = 0; $i < 28; $i++): ?><i style="--x:<?= random_int(0, 100) ?>%;--d:<?= random_int(0, 1400) ?>ms;--r:<?= random_int(0, 360) ?>deg;--c:<?= ['#e3f264', '#2446d8', '#ffffff', '#1f8a5b'][$i % 4] ?>"></i><?php endfor; ?></div>
    <button class="modal-close" data-close aria-label="Закрыть">×</button>
    <div class="celebrate-ic">✓</div>
    <h2>Поздравляем!</h2>
    <p>Оплата прошла успешно. Полный курс открыт — можешь приступать к урокам обучения.</p>
    <?php $firstPaid = null; foreach (course_lessons() as $l) { if (!(int)$l['is_free'] && can_view_lesson($user, $l)) { $firstPaid = $l; break; } } ?>
    <a class="btn btn-blue" href="<?= url($firstPaid ? 'lesson.php?id=' . $firstPaid['id'] : 'index.php') ?>">Начать обучение →</a>
  </div>
</div>
<?php elseif ($upsellPopup): ?>
<div class="modal open" role="dialog" aria-modal="true" aria-label="Поздравляем">
  <div class="modal-box wide">
    <button class="modal-close" data-close aria-label="Закрыть">×</button>
    <?php upsell_block($payHref, true); ?>
  </div>
</div>
<?php endif; ?>

<?php if ($upsell) upsell_block($payHref, false); ?>
<?php if ($user['role'] === 'admin'): ?>
  <div class="flash flash-ok">Вы вошли как <b>администратор</b>, поэтому вам открыты все уроки и модули. Ученики видят только первый урок, а остальные открываются по мере проверки домашних заданий и после оплаты. Чтобы посмотреть кабинет глазами ученика, зарегистрируйтесь с другой почтой в окне «инкогнито».</div>
<?php endif; ?>

<section class="hello reveal">
  <div>
    <span class="pill" style="border-color:rgba(255,255,255,.5);color:#fff">Личный кабинет</span>
    <h1 style="margin-top:20px">Привет, <em><?= e($first) ?></em>!</h1>
    <?php if ($cert): ?>
      <p>Курс пройден — ваш сертификат готов.</p>
      <a class="btn btn-lime" href="<?= url('certificate.php?c=' . $cert['code']) ?>">Открыть сертификат</a>
    <?php elseif ($next): ?>
      <p><?= $states[$next['id']] === 'returned' ? 'Есть задание на доработку:' : 'Продолжим обучение:' ?> «<?= e($next['title']) ?>»</p>
      <a class="btn btn-lime" href="<?= url('lesson.php?id=' . $next['id']) ?>"><?= $states[$next['id']] === 'returned' ? 'Доработать' : 'Продолжить урок' ?> →</a>
    <?php elseif ($pending): ?>
      <p>Домашние задания на проверке. Следующий урок откроется, как только администратор отметит все пункты.</p>
    <?php elseif (course_completed($user)): ?>
      <p>Все задания приняты — осталось пройти итоговый тест.</p>
      <a class="btn btn-lime" href="<?= url('test.php') ?>">Пройти тест →</a>
    <?php elseif ($upsell): ?>
      <p>Бесплатные уроки пройдены — отличная работа! Открой полный курс, чтобы продолжить.</p>
      <a class="btn btn-lime" href="<?= e($payHref) ?>" target="_blank" rel="noopener">Перейти к оплате →</a>
    <?php else: ?>
      <p>Продолжай обучение — следующий шаг уже ждёт.</p>
    <?php endif; ?>
  </div>
  <div class="ring" style="--p:<?= $pct ?>"><div><span><b><?= $pct ?>%</b><small>курса пройдено</small></span></div></div>
</section>

<div class="stats">
  <div class="stat lime reveal"><b><?= $done ?>/<?= $total ?></b><span>заданий принято</span></div>
  <div class="stat reveal"><b><?= $pending ?></b><span><?= plural($pending, 'задание', 'задания', 'заданий') ?> на проверке</span></div>
  <div class="stat reveal"><b><?= count(array_filter($mods, fn($m) => has_module_access($user, $m))) ?>/<?= count($mods) ?></b><span>модулей открыто</span></div>
  <div class="stat reveal"><b><?= $cert ? '✓' : '—' ?></b><span>сертификат</span></div>
</div>

<div class="section-title">
  <h2>Программа курса</h2>
  <span class="muted small">Нажмите на модуль, чтобы увидеть уроки</span>
</div>

<div class="mods">
<?php
$colors = ['tab-lime', 'tab-blue'];
$openSet = false;
foreach ($mods as $i => $m):
    $lessons = module_lessons((int)$m['id']);
    $access = has_module_access($user, $m);
    $mDone = 0; $mHw = 0;
    foreach ($lessons as $l) { if (hw_count((int)$l['id'])) { $mHw++; if ($states[$l['id']] === 'accepted') $mDone++; } }
    // open the module that contains the next lesson
    $isOpen = !$openSet && $next && (int)$next['module_id'] === (int)$m['id'];
    if ($isOpen) $openSet = true;
    $cls = $i === count($mods) - 1 ? 'tab-ink' : $colors[$i % 2];
?>
  <div class="mod <?= $cls ?><?= $isOpen ? ' open' : '' ?> reveal">
    <button class="mod-head" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>">
      <span class="num">Модуль <?= $i + 1 ?></span>
      <span class="name"><?= e($m['title']) ?><small><?= e($m['subtitle']) ?></small></span>
      <span class="mod-meta">
        <?php if ($m['is_free']): ?><span class="chip chip-free">Бесплатно</span>
        <?php elseif (!$access): ?><span class="chip chip-lock">Платный</span>
        <?php else: ?><span class="chip chip-open">Открыт</span><?php endif; ?>
        <span><?= count($lessons) ?> <?= plural(count($lessons), 'урок', 'урока', 'уроков') ?></span>
        <?php if ($mHw): ?><span class="bar"><i style="width:<?= round($mDone * 100 / $mHw) ?>%"></i></span><?php endif; ?>
      </span>
      <span class="plus">+</span>
    </button>
    <div class="mod-body"><div>
      <div class="lessons">
        <?php foreach ($lessons as $li => $l): $st = $states[$l['id']]; $locked = in_array($st, ['pay', 'wait'], true); ?>
          <?php if ($locked): ?>
            <div class="lrow locked" title="<?= e(STATE_LABELS[$st][0]) ?>"><span class="n"><?= $li + 1 ?></span><span class="t"><?= e($l['title']) ?></span><?= state_chip($st) ?><span class="go">🔒</span></div>
          <?php else: ?>
            <a class="lrow" href="<?= url('lesson.php?id=' . $l['id']) ?>"><span class="n"><?= $li + 1 ?></span><span class="t"><?= e($l['title']) ?></span><?= state_chip($st) ?><span class="go">→</span></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php if (!$access): ?>
        <div class="mod-pay">
          <div><b>Модуль откроется после оплаты</b><div class="small" style="opacity:.8"><?= sold_with_course($m) ? 'Полный курс ' . e(price_of($m)) . ' · ' . e(setting('price_note')) : e(price_of($m)) ?></div></div>
          <a class="btn btn-lime btn-sm" href="<?= url('pay.php?m=' . $m['id']) ?>">Открыть модуль</a>
        </div>
      <?php endif; ?>
    </div></div>
  </div>
<?php endforeach; ?>
</div>

<div class="grid2 mt">
  <div class="card lime reveal">
    <h2>Итоговый тест</h2>
    <p class="mt" style="margin-top:12px">Проверка знаний и короткая обратная связь: что узнали, что поняли, что осталось непонятным.</p>
    <a class="btn btn-ink mt" href="<?= url('test.php') ?>"><?= $cert ? 'Результаты теста' : 'К тесту' ?></a>
  </div>
  <div class="card blue reveal">
    <h2>Сертификат</h2>
    <p style="margin-top:12px;opacity:.85">Выдаётся после принятия всех домашних заданий и успешного теста.</p>
    <?php if ($cert): ?><a class="btn btn-lime mt" href="<?= url('certificate.php?c=' . $cert['code']) ?>">Открыть сертификат</a>
    <?php else: ?><span class="btn btn-line mt" style="color:#fff;cursor:default">Пока недоступен</span><?php endif; ?>
  </div>
</div>

<?php layout_foot();
