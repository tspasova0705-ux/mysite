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

layout_head('Мои уроки', 'home'); ?>

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
    <?php else: ?>
      <p>Бесплатные уроки пройдены. Откройте следующий модуль, чтобы продолжить.</p>
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
          <div><b>Модуль откроется после оплаты</b><div class="small" style="opacity:.8"><?= e($m['price_label']) ?></div></div>
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
