<?php
require __DIR__ . '/inc/bootstrap.php';
$user = require_login();

$questions = all('SELECT * FROM test_questions ORDER BY position, id');
$cert = one('SELECT * FROM certificates WHERE user_id=?', [$user['id']]);
$eligible = $user['role'] === 'admin' || course_completed($user);
$pass = (int)setting('pass_percent', '70');
$result = null;

if (is_post() && $eligible && !$cert) {
    csrf_check();
    $score = 0; $total = 0; $saved = []; $missing = false;
    foreach ($questions as $qq) {
        $ans = $_POST['q'][$qq['id']] ?? null;
        if ($qq['type'] === 'single') {
            $total++;
            if ($ans === null || $ans === '') $missing = true;
            elseif ((int)$ans === (int)$qq['correct']) $score++;
            $saved[$qq['id']] = $ans === null ? null : (int)$ans;
        } else {
            $txt = mb_substr(trim((string)$ans), 0, 4000);
            if (mb_strlen($txt) < 3) $missing = true;
            $saved[$qq['id']] = $txt;
        }
    }
    if ($missing) {
        flash('Ответьте на все вопросы, включая вопросы для обратной связи.', 'err');
        $_SESSION['test_draft'] = $_POST['q'] ?? [];
        redirect('test.php');
    }
    $pct = $total ? (int)round($score * 100 / $total) : 100;
    $passed = $pct >= $pass;
    q('INSERT INTO test_attempts(user_id,score,total,passed,answers,created_at) VALUES(?,?,?,?,?,?)',
        [$user['id'], $score, $total, $passed ? 1 : 0, json_encode($saved, JSON_UNESCAPED_UNICODE), now()]);
    if ($passed) {
        $code = 'BP-' . strtoupper(bin2hex(random_bytes(4)));
        q('INSERT INTO certificates(user_id,code,score,issued_at) VALUES(?,?,?,?)', [$user['id'], $code, $pct, now()]);
        flash('Поздравляем! Тест пройден, сертификат выдан.');
    } else {
        flash("Результат $pct% — для сертификата нужно $pass%. Повторите материал и попробуйте ещё раз.", 'err');
    }
    $_SESSION['last_attempt'] = (int)db()->lastInsertId();
    redirect('test.php');
}

$last = one('SELECT * FROM test_attempts WHERE user_id=? ORDER BY id DESC LIMIT 1', [$user['id']]);
$lastAnswers = $last ? json_decode($last['answers'], true) : [];
$showReview = $last && (!empty($_SESSION['last_attempt']) || $cert);
$draft = $_SESSION['test_draft'] ?? []; unset($_SESSION['test_draft']);
$singles = count(array_filter($questions, fn($x) => $x['type'] === 'single'));

layout_head('Итоговый тест', 'test'); ?>

<section class="lesson-head reveal">
  <div>
    <div class="num">Финал курса</div>
    <h1>Итоговый тест</h1>
    <p style="margin-top:12px;opacity:.85;max-width:620px"><?= $singles ?> <?= plural($singles, 'вопрос', 'вопроса', 'вопросов') ?> на знание материала и короткая обратная связь. Для сертификата нужно <?= $pass ?>% правильных ответов.</p>
  </div>
  <?php if ($cert): ?><a class="btn btn-lime" href="<?= url('certificate.php?c=' . $cert['code']) ?>">Мой сертификат</a><?php endif; ?>
</section>

<?php if (!$eligible): ?>
  <div class="card mt reveal" style="text-align:center;padding:50px 24px">
    <h2>Тест пока закрыт</h2>
    <p class="muted" style="margin:12px auto 24px;max-width:520px">Он откроется, когда администратор примет домашние задания по всем урокам курса.</p>
    <a class="btn btn-blue" href="<?= url('index.php') ?>">К урокам</a>
  </div>
<?php elseif ($cert): ?>
  <div class="grid2 mt">
    <div class="card lime reveal"><h2>Результат: <?= (int)$cert['score'] ?>%</h2><p style="margin-top:10px">Тест пройден <?= e(date('d.m.Y', strtotime($cert['issued_at']))) ?>.</p></div>
    <div class="card blue reveal"><h2>Сертификат № <?= e($cert['code']) ?></h2><a class="btn btn-lime mt" href="<?= url('certificate.php?c=' . $cert['code']) ?>">Открыть и скачать</a></div>
  </div>
<?php else: ?>
  <form method="post" class="qs mt">
    <?= csrf_field() ?>
    <?php $n = 0; foreach ($questions as $qq): $n++; $opts = json_decode($qq['options'], true) ?: []; $prevAns = $draft[$qq['id']] ?? null; ?>
      <div class="qcard reveal">
        <div class="qn"><?= $qq['type'] === 'open' ? 'Обратная связь' : 'Вопрос ' . $n ?></div>
        <div class="qt"><?= e($qq['text']) ?></div>
        <?php if ($qq['type'] === 'single'): ?>
          <div class="opts">
            <?php foreach ($opts as $oi => $o):
              $cls = '';
              if ($showReview && isset($lastAnswers[$qq['id']]) && (int)$lastAnswers[$qq['id']] === $oi) $cls = $oi === (int)$qq['correct'] ? 'right' : 'wrong'; ?>
              <label class="opt <?= $cls ?>"><input type="radio" name="q[<?= $qq['id'] ?>]" value="<?= $oi ?>" <?= (string)$prevAns === (string)$oi ? 'checked' : '' ?> required> <?= e($o) ?></label>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <textarea name="q[<?= $qq['id'] ?>]" required placeholder="Ваш ответ"><?= e((string)($prevAns ?? '')) ?></textarea>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <div class="row between card gray">
      <span class="small"><?= $last ? 'Предыдущая попытка: ' . (int)round($last['score'] * 100 / max(1, $last['total'])) . '%. Подсвечены ваши прошлые ответы.' : 'Проверьте ответы перед отправкой.' ?></span>
      <button class="btn btn-blue" type="submit">Завершить тест</button>
    </div>
  </form>
<?php endif; ?>

<?php unset($_SESSION['last_attempt']); layout_foot();
