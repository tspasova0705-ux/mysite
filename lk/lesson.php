<?php
require __DIR__ . '/inc/bootstrap.php';
$user = require_login();

$lesson = one('SELECT * FROM lessons WHERE id=?', [get_int('id')]);
if (!$lesson) { flash('Урок не найден.', 'err'); redirect('index.php'); }
$module = one('SELECT * FROM modules WHERE id=?', [$lesson['module_id']]);
$state = lesson_state($user, $lesson);
if ($state === 'pay') redirect('pay.php?m=' . $module['id']);
if ($state === 'wait') { flash('Этот урок откроется, когда администратор примет домашнее задание предыдущего урока.', 'err'); redirect('index.php'); }

$items = all('SELECT * FROM hw_items WHERE lesson_id=? ORDER BY position, id', [$lesson['id']]);
$sub = submission_for((int)$user['id'], (int)$lesson['id']);
$answers = [];
if ($sub) foreach (all('SELECT a.*, f.orig_name FROM answers a LEFT JOIN files f ON f.id=a.file_id WHERE submission_id=?', [$sub['id']]) as $a) $answers[$a['item_id']] = $a;

/* ---------- homework submit ---------- */
if (is_post() && $items) {
    csrf_check();
    if ($sub && $sub['status'] === 'accepted') redirect('lesson.php?id=' . $lesson['id']);
    try {
        db()->beginTransaction();
        if (!$sub) {
            q("INSERT INTO submissions(user_id,lesson_id,status,updated_at) VALUES(?,?,'submitted',?)", [$user['id'], $lesson['id'], now()]);
            $subId = (int)db()->lastInsertId();
        } else $subId = (int)$sub['id'];

        $missing = 0;
        foreach ($items as $it) {
            $prev = $answers[$it['id']] ?? null;
            if ($prev && $prev['mark'] === 'done') continue; // accepted items stay as they are
            $text = mb_substr(trim((string)($_POST['a'][$it['id']] ?? '')), 0, 8000);
            $fileId = $prev['file_id'] ?? null;
            $up = isset($_FILES['f']['name'][$it['id']]) ? [
                'name' => $_FILES['f']['name'][$it['id']], 'type' => $_FILES['f']['type'][$it['id']],
                'tmp_name' => $_FILES['f']['tmp_name'][$it['id']], 'error' => $_FILES['f']['error'][$it['id']], 'size' => $_FILES['f']['size'][$it['id']],
            ] : ['error' => UPLOAD_ERR_NO_FILE];
            $newFile = store_upload($up, 'hw', (int)$lesson['id'], (int)$user['id']);
            if ($newFile) { if ($fileId) delete_file((int)$fileId); $fileId = $newFile; }
            if ($text === '' && !$fileId) { $missing++; continue; }
            q("INSERT INTO answers(submission_id,item_id,answer_text,file_id,mark,admin_note) VALUES(?,?,?,?,'pending','')
               ON CONFLICT(submission_id,item_id) DO UPDATE SET answer_text=excluded.answer_text, file_id=excluded.file_id, mark='pending'",
               [$subId, $it['id'], $text, $fileId]);
        }
        if ($missing) throw new RuntimeException('Заполните все пункты задания: напишите ответ или прикрепите файл.');
        q('UPDATE submissions SET updated_at=? WHERE id=?', [now(), $subId]);
        refresh_submission($subId);
        db()->commit();
        flash('Задание отправлено на проверку. Мы сообщим результат здесь, в уроке.');
    } catch (RuntimeException $ex) {
        db()->rollBack();
        flash($ex->getMessage(), 'err');
    }
    redirect('lesson.php?id=' . $lesson['id'] . '#homework');
}

/* ---------- view data ---------- */
$materials = all("SELECT * FROM files WHERE lesson_id=? AND kind='material' ORDER BY id", [$lesson['id']]);
$siblings = module_lessons((int)$module['id']);
$modIndex = (int)val('SELECT COUNT(*) FROM modules WHERE position < ? OR (position = ? AND id < ?)', [$module['position'], $module['position'], $module['id']]) + 1;
$all = course_lessons();
$pos = array_search((int)$lesson['id'], array_map(fn($l) => (int)$l['id'], $all), true);
$prevL = $pos > 0 ? $all[$pos - 1] : null;
$nextL = $all[$pos + 1] ?? null;
$nextOpen = $nextL && can_view_lesson($user, $nextL);
$lessonNo = array_search((int)$lesson['id'], array_map(fn($l) => (int)$l['id'], $siblings), true) + 1;
$editable = !$sub || $sub['status'] !== 'accepted';
$embed = $lesson['video_url'] ? video_embed($lesson['video_url']) : null;

layout_head($lesson['title'], 'home'); ?>

<div class="crumbs"><a href="<?= url('index.php') ?>">Мои уроки</a><span>/</span><span>Модуль <?= $modIndex ?>. <?= e($module['title']) ?></span></div>

<section class="lesson-head reveal">
  <div>
    <div class="num">Модуль <?= $modIndex ?> · Урок <?= $lessonNo ?></div>
    <h1><?= e($lesson['title']) ?></h1>
  </div>
  <?= state_chip($items ? $state : 'open') ?>
</section>

<div class="lesson-grid">
  <div class="stack-sm">
    <?php if ($lesson['video_file_id']): ?>
      <div class="video reveal"><video controls preload="metadata" controlsList="nodownload" src="<?= url('file.php?id=' . $lesson['video_file_id']) ?>"></video></div>
    <?php elseif ($embed): ?>
      <div class="video reveal"><iframe src="<?= e($embed) ?>" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" allowfullscreen></iframe></div>
    <?php else: ?>
      <div class="video-empty reveal"><div><b>Видео скоро появится</b>Пока изучите конспект и материалы ниже.</div></div>
    <?php endif; ?>

    <?php if ($materials): ?>
    <div class="card reveal">
      <h2>Материалы</h2>
      <div class="files mt" style="margin-top:16px">
        <?php foreach ($materials as $f): $ext = strtolower(pathinfo($f['orig_name'], PATHINFO_EXTENSION)); ?>
          <div class="file">
            <span class="ext"><?= e(mb_substr($ext ?: 'file', 0, 4)) ?></span>
            <div>
              <b><?= e($f['orig_name']) ?></b><small><?= human_size((int)$f['size']) ?></small>
              <?php if ($f['comment']): ?><div class="comment"><?= nl2br(e($f['comment'])) ?></div><?php endif; ?>
            </div>
            <a class="btn btn-blue btn-sm" href="<?= url('file.php?id=' . $f['id'] . '&dl=1') ?>">Скачать</a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (trim($lesson['body']) !== ''): ?>
    <details class="box reveal" open>
      <summary>Конспект урока</summary>
      <div class="body"><?= render_body($lesson['body']) ?></div>
    </details>
    <?php endif; ?>

    <?php if ($items): ?>
    <form class="hw reveal" id="homework" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="hw-head">
        <div>
          <h2>Домашнее задание</h2>
          <p style="margin-top:8px;max-width:560px"><?= e($lesson['homework_intro']) ?></p>
        </div>
        <?= state_chip($sub ? $sub['status'] : 'open') ?>
      </div>
      <?php if ($sub && $sub['admin_comment']): ?><div class="note ok" style="margin-bottom:12px;background:#fff;color:var(--ink)"><b>Комментарий администратора:</b> <?= nl2br(e($sub['admin_comment'])) ?></div><?php endif; ?>
      <div class="hw-items">
        <?php foreach ($items as $k => $it):
            $a = $answers[$it['id']] ?? null;
            $mark = $a['mark'] ?? 'new';
            $canEdit = $editable && $mark !== 'done' && !($mark === 'pending' && $sub && $sub['status'] === 'submitted');
        ?>
          <div class="hw-item <?= $mark === 'done' ? 'is-done' : ($mark === 'redo' ? 'is-redo' : '') ?>">
            <div class="hw-item-top">
              <span class="n"><?= $mark === 'done' ? '✓' : $k + 1 ?></span>
              <span class="q"><?= e($it['text']) ?></span>
              <?= $mark === 'done' ? state_chip('accepted') : ($mark === 'redo' ? state_chip('returned') : ($mark === 'pending' ? state_chip('submitted') : '')) ?>
            </div>
            <?php if ($a && $a['admin_note']): ?><div class="note <?= $mark === 'done' ? 'ok' : '' ?>"><?= nl2br(e($a['admin_note'])) ?></div><?php endif; ?>
            <?php if ($canEdit): ?>
              <textarea name="a[<?= $it['id'] ?>]" placeholder="Ваш ответ"><?= e($a['answer_text'] ?? '') ?></textarea>
              <div class="file-input">
                <input type="file" name="f[<?= $it['id'] ?>]">
                <?php if (!empty($a['file_id'])): ?><a class="small" href="<?= url('file.php?id=' . $a['file_id'] . '&dl=1') ?>">📎 <?= e($a['orig_name']) ?></a><?php endif; ?>
              </div>
            <?php elseif ($a): ?>
              <?php if ($a['answer_text'] !== ''): ?><div class="hw-answer"><?= e($a['answer_text']) ?></div><?php endif; ?>
              <?php if ($a['file_id']): ?><a class="small" style="display:inline-block;margin-top:8px" href="<?= url('file.php?id=' . $a['file_id'] . '&dl=1') ?>">📎 <?= e($a['orig_name']) ?></a><?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="hw-foot">
        <?php if (!$sub): ?>
          <span class="small">Отвечайте текстом или прикрепляйте файл/скриншот к каждому пункту.</span>
          <button class="btn btn-blue" type="submit">Отправить на проверку</button>
        <?php elseif ($sub['status'] === 'returned'): ?>
          <span class="small"><b>Исправьте отмеченные пункты</b> и отправьте задание ещё раз.</span>
          <button class="btn btn-blue" type="submit">Отправить повторно</button>
        <?php elseif ($sub['status'] === 'submitted'): ?>
          <span class="small"><b>Задание на проверке.</b> Администратор отметит каждый пункт.</span>
        <?php else: ?>
          <span class="small"><b>Все пункты выполнены.</b> Отличная работа!</span>
        <?php endif; ?>
      </div>
    </form>
    <?php endif; ?>

    <div class="pager">
      <?php if ($prevL): ?><a class="btn btn-line btn-sm" href="<?= url('lesson.php?id=' . $prevL['id']) ?>">← Предыдущий урок</a><?php else: ?><span></span><?php endif; ?>
      <?php if ($nextL && $nextOpen): ?><a class="btn btn-blue btn-sm" href="<?= url('lesson.php?id=' . $nextL['id']) ?>">Следующий урок →</a>
      <?php elseif ($nextL && lesson_state($user, $nextL) === 'pay'): ?><a class="btn btn-lime btn-sm" href="<?= url('pay.php?m=' . $nextL['module_id']) ?>">Открыть следующий модуль →</a>
      <?php elseif ($nextL): ?><span class="btn btn-line btn-sm" title="Откроется после проверки задания" style="opacity:.5;cursor:default">Следующий урок 🔒</span>
      <?php else: ?><a class="btn btn-lime btn-sm" href="<?= url('test.php') ?>">К итоговому тесту →</a><?php endif; ?>
    </div>
  </div>

  <aside class="side">
    <div class="card">
      <h3>Модуль <?= $modIndex ?></h3>
      <div class="small muted" style="margin-top:4px"><?= e($module['title']) ?></div>
      <div class="side-list">
        <?php foreach ($siblings as $i => $s): $st = (int)$s['id'] === (int)$lesson['id'] ? $state : lesson_state($user, $s);
          $dot = ['accepted' => 'done', 'submitted' => 'submitted', 'returned' => 'returned', 'open' => 'open'][$st] ?? '';
          $lockedS = in_array($st, ['pay', 'wait'], true); ?>
          <?php if ($lockedS): ?>
            <span class="lk"><span class="n"><?= $i + 1 ?></span><span><?= e($s['title']) ?></span><span>🔒</span></span>
          <?php else: ?>
            <a href="<?= url('lesson.php?id=' . $s['id']) ?>" class="<?= (int)$s['id'] === (int)$lesson['id'] ? 'cur' : '' ?>"><span class="n"><?= $i + 1 ?></span><span><?= e($s['title']) ?></span><span class="dot <?= $dot ?>"></span></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card gray small">
      <b>Как проходит проверка</b>
      <p class="muted" style="margin-top:6px">Администратор отмечает каждый пункт задания: «Выполнено» или «Доработать» с комментарием. Следующий урок откроется, когда все пункты будут выполнены.</p>
    </div>
  </aside>
</div>

<?php layout_foot();
