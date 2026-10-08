<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();

$sub = one('SELECT s.*, u.name, u.email, l.title, l.id AS lid FROM submissions s JOIN users u ON u.id=s.user_id JOIN lessons l ON l.id=s.lesson_id WHERE s.id=?', [get_int('id')]);
if (!$sub) redirect('admin/homework.php');

if (is_post()) {
    csrf_check();
    foreach ($_POST['mark'] ?? [] as $answerId => $mark) {
        if (!in_array($mark, ['pending', 'done', 'redo'], true)) continue;
        q('UPDATE answers SET mark=?, admin_note=? WHERE id=? AND submission_id=?',
            [$mark, mb_substr(trim((string)($_POST['note'][$answerId] ?? '')), 0, 2000), (int)$answerId, $sub['id']]);
    }
    q('UPDATE submissions SET admin_comment=? WHERE id=?', [mb_substr(post('comment'), 0, 3000), $sub['id']]);
    $status = refresh_submission((int)$sub['id']);
    flash(['accepted' => 'Задание принято — следующий урок открыт ученику.', 'returned' => 'Задание отправлено на доработку.', 'submitted' => 'Отметки сохранены.'][$status]);
    // go to the next submission waiting for review
    $next = val("SELECT id FROM submissions WHERE status='submitted' AND id<>? ORDER BY updated_at LIMIT 1", [$sub['id']]);
    redirect($next && isset($_POST['and_next']) ? 'admin/review.php?id=' . $next : 'admin/homework.php');
}

$items = all('SELECT h.*, a.id AS aid, a.answer_text, a.file_id, a.mark, a.admin_note, f.orig_name, f.size
    FROM hw_items h LEFT JOIN answers a ON a.item_id=h.id AND a.submission_id=? LEFT JOIN files f ON f.id=a.file_id
    WHERE h.lesson_id=? ORDER BY h.position, h.id', [$sub['id'], $sub['lid']]);

layout_head('Проверка задания', 'homework'); ?>
<div class="crumbs"><a href="<?= url('admin/homework.php') ?>">Домашние задания</a><span>/</span><span><?= e($sub['name']) ?></span></div>

<section class="lesson-head">
  <div><div class="num"><?= e($sub['name']) ?> · <?= e($sub['email']) ?></div><h1><?= e($sub['title']) ?></h1></div>
  <?= state_chip($sub['status']) ?>
</section>

<form method="post" class="hw mt">
  <?= csrf_field() ?>
  <div class="hw-head">
    <div><h2>Графы задания</h2><p style="margin-top:6px">Отметьте каждый пункт. Если что-то нужно исправить — выберите «Доработать» и напишите комментарий.</p></div>
    <button type="button" class="btn btn-ink btn-sm" data-mark-all="done">Отметить всё «Выполнено»</button>
  </div>
  <div class="hw-items">
    <?php foreach ($items as $k => $it): ?>
      <div class="hw-item <?= $it['mark'] === 'done' ? 'is-done' : ($it['mark'] === 'redo' ? 'is-redo' : '') ?>">
        <div class="hw-item-top"><span class="n"><?= $k + 1 ?></span><span class="q"><?= e($it['text']) ?></span></div>
        <?php if (!$it['aid']): ?>
          <div class="note">Ученик ещё не ответил на этот пункт.</div>
        <?php else: ?>
          <?php if ($it['answer_text'] !== ''): ?><div class="hw-answer"><?= e($it['answer_text']) ?></div><?php endif; ?>
          <?php if ($it['file_id']): ?><div style="margin-top:8px"><a class="pill" href="<?= url('file.php?id=' . $it['file_id']) ?>" target="_blank">📎 <?= e($it['orig_name']) ?> · <?= human_size((int)$it['size']) ?></a></div><?php endif; ?>
          <div class="row" style="margin-top:12px">
            <div class="mark-group">
              <label><input type="radio" name="mark[<?= $it['aid'] ?>]" value="done" <?= $it['mark'] === 'done' ? 'checked' : '' ?>>✓ Выполнено</label>
              <label><input type="radio" name="mark[<?= $it['aid'] ?>]" value="redo" <?= $it['mark'] === 'redo' ? 'checked' : '' ?>>↺ Доработать</label>
              <label><input type="radio" name="mark[<?= $it['aid'] ?>]" value="pending" <?= $it['mark'] === 'pending' ? 'checked' : '' ?>>Позже</label>
            </div>
          </div>
          <input type="text" name="note[<?= $it['aid'] ?>]" value="<?= e($it['admin_note']) ?>" placeholder="Комментарий к пункту (ученик его увидит)" style="margin-top:10px">
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <label class="field mt">Общий комментарий к заданию<textarea name="comment" placeholder="Например: отличная работа, особенно пункт 3!"><?= e($sub['admin_comment']) ?></textarea></label>
  <div class="hw-foot">
    <a class="btn btn-line btn-sm" href="<?= url('admin/homework.php') ?>">Назад</a>
    <div class="row">
      <button class="btn btn-line" type="submit">Сохранить</button>
      <button class="btn btn-blue" type="submit" name="and_next" value="1">Сохранить и к следующему</button>
    </div>
  </div>
</form>
<?php layout_foot();
