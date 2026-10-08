<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();

$lesson = one('SELECT * FROM lessons WHERE id=?', [get_int('id')]);
if (!$lesson) redirect('admin/course.php');
$lid = (int)$lesson['id'];

if (is_post()) {
    csrf_check();
    try {
        switch (post('action')) {
            case 'save':
                q('UPDATE lessons SET title=?, module_id=?, body=?, video_url=?, homework_intro=? WHERE id=?',
                    [post('title') ?: 'Урок', (int)post('module_id'), (string)($_POST['body'] ?? ''), post('video_url'), post('homework_intro'), $lid]);
                if ((int)post('module_id') !== (int)$lesson['module_id']) {
                    q('UPDATE lessons SET position=(SELECT COALESCE(MAX(position),0)+1 FROM lessons WHERE module_id=?) WHERE id=?', [(int)post('module_id'), $lid]);
                }
                // homework items: edit, delete, add
                foreach ($_POST['item'] ?? [] as $itemId => $text) {
                    $text = trim((string)$text);
                    if (isset($_POST['item_del'][$itemId]) || $text === '') q('DELETE FROM hw_items WHERE id=? AND lesson_id=?', [(int)$itemId, $lid]);
                    else q('UPDATE hw_items SET text=? WHERE id=? AND lesson_id=?', [$text, (int)$itemId, $lid]);
                }
                $pos = (int)val('SELECT COALESCE(MAX(position),0) FROM hw_items WHERE lesson_id=?', [$lid]);
                foreach (preg_split('~\R~', (string)($_POST['new_items'] ?? '')) as $line) {
                    if (($line = trim($line)) !== '') q('INSERT INTO hw_items(lesson_id,position,text) VALUES(?,?,?)', [$lid, ++$pos, $line]);
                }
                flash('Урок сохранён.');
                break;
            case 'upload_video':
                $fid = store_upload($_FILES['video'] ?? [], 'video', $lid, null, '', VIDEO_EXT);
                if (!$fid) throw new RuntimeException('Выберите видеофайл.');
                if ($lesson['video_file_id']) delete_file((int)$lesson['video_file_id']);
                q('UPDATE lessons SET video_file_id=? WHERE id=?', [$fid, $lid]);
                flash('Видео загружено.');
                break;
            case 'remove_video':
                if ($lesson['video_file_id']) delete_file((int)$lesson['video_file_id']);
                q('UPDATE lessons SET video_file_id=NULL WHERE id=?', [$lid]);
                flash('Видеофайл удалён.');
                break;
            case 'upload_material':
                $fid = store_upload($_FILES['material'] ?? [], 'material', $lid, null, mb_substr(post('comment'), 0, 2000));
                if (!$fid) throw new RuntimeException('Выберите файл.');
                flash('Файл добавлен.');
                break;
            case 'save_comment':
                q("UPDATE files SET comment=? WHERE id=? AND lesson_id=? AND kind='material'", [mb_substr(post('comment'), 0, 2000), (int)post('file_id'), $lid]);
                flash('Комментарий сохранён.');
                break;
            case 'delete_material':
                if (val("SELECT 1 FROM files WHERE id=? AND lesson_id=? AND kind='material'", [(int)post('file_id'), $lid])) delete_file((int)post('file_id'));
                flash('Файл удалён.');
                break;
        }
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'err');
    }
    redirect('admin/lesson.php?id=' . $lid);
}

$items = all('SELECT * FROM hw_items WHERE lesson_id=? ORDER BY position, id', [$lid]);
$materials = all("SELECT * FROM files WHERE lesson_id=? AND kind='material' ORDER BY id", [$lid]);
$video = $lesson['video_file_id'] ? one('SELECT * FROM files WHERE id=?', [$lesson['video_file_id']]) : null;
$embed = video_embed($lesson['video_url']);
$limit = 'до ' . ini_get('upload_max_filesize') . ' (лимит хостинга)';

layout_head('Урок: ' . $lesson['title'], 'course'); ?>
<div class="crumbs"><a href="<?= url('admin/course.php') ?>">Курс</a><span>/</span><span><?= e($lesson['title']) ?></span></div>
<div class="row between" style="margin-bottom:16px">
  <h1><?= e($lesson['title']) ?></h1>
  <div class="row">
    <a class="pill" href="<?= url('lesson.php?id=' . $lid) ?>" target="_blank">Посмотреть как ученик ↗</a>
    <form method="post" action="<?= url('admin/course.php') ?>" data-confirm="Удалить урок со всеми файлами и ответами учеников?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $lid ?>"><button class="pill" style="border-color:#c0392b;color:#c0392b" name="action" value="delete_lesson">Удалить урок</button></form>
  </div>
</div>

<div class="lesson-grid wide-side">
  <div class="stack-sm">
    <!-- VIDEO -->
    <div class="card">
      <h2>Видео</h2>
      <div class="mt" style="margin-top:14px">
        <?php if ($video): ?>
          <div class="video"><video controls preload="metadata" src="<?= url('file.php?id=' . $video['id']) ?>"></video></div>
          <form method="post" class="row between" style="margin-top:10px"><?= csrf_field() ?><span class="small muted">Файл: <?= e($video['orig_name']) ?> · <?= human_size((int)$video['size']) ?></span><button class="btn btn-danger btn-sm" name="action" value="remove_video" data-confirm="Удалить видеофайл?">Удалить видео</button></form>
        <?php elseif ($embed): ?>
          <div class="video"><iframe src="<?= e($embed) ?>" allowfullscreen></iframe></div>
        <?php else: ?>
          <div class="video-empty"><div><b>Видео не добавлено</b>Вставьте ссылку ниже или загрузите файл</div></div>
        <?php endif; ?>
      </div>
      <p class="small muted mt" style="margin-top:16px"><b>Способ 1 — ссылка</b> (рекомендуем): загрузите видео на Kinescope, Rutube, VK Видео или YouTube с ограниченным доступом и вставьте ссылку в поле «Ссылка на видео» ниже, в форме урока.</p>
      <form method="post" enctype="multipart/form-data" class="file-input" style="margin-top:12px">
        <?= csrf_field() ?>
        <span class="small muted"><b>Способ 2 — файл</b> mp4/mov/webm, <?= e($limit) ?>:</span>
        <input type="file" name="video" accept="video/mp4,video/quicktime,video/webm,.m4v" required>
        <button class="btn btn-blue btn-sm" name="action" value="upload_video">Загрузить видео</button>
      </form>
    </div>

    <!-- MATERIALS -->
    <div class="card">
      <h2>Файлы к уроку</h2>
      <div class="files mt" style="margin-top:14px">
        <?php if (!$materials): ?><p class="muted small">Файлов пока нет.</p><?php endif; ?>
        <?php foreach ($materials as $f): ?>
          <div class="file" style="grid-template-columns:46px 1fr">
            <span class="ext"><?= e(mb_substr(strtolower(pathinfo($f['orig_name'], PATHINFO_EXTENSION)) ?: 'file', 0, 4)) ?></span>
            <form method="post">
              <?= csrf_field() ?><input type="hidden" name="file_id" value="<?= $f['id'] ?>">
              <b><?= e($f['orig_name']) ?></b><small><?= human_size((int)$f['size']) ?></small>
              <textarea name="comment" placeholder="Комментарий к файлу" style="min-height:60px;margin-top:8px"><?= e($f['comment']) ?></textarea>
              <div class="row" style="margin-top:8px">
                <button class="btn btn-blue btn-sm" name="action" value="save_comment">Сохранить комментарий</button>
                <a class="btn btn-line btn-sm" href="<?= url('file.php?id=' . $f['id'] . '&dl=1') ?>">Скачать</a>
                <button class="btn btn-danger btn-sm" name="action" value="delete_material" data-confirm="Удалить файл?">Удалить</button>
              </div>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
      <form method="post" enctype="multipart/form-data" class="card gray mt" style="padding:20px">
        <?= csrf_field() ?>
        <h3>Добавить файл</h3>
        <div class="file-input" style="margin:12px 0"><input type="file" name="material" required><span class="small muted"><?= e($limit) ?></span></div>
        <textarea name="comment" placeholder="Комментарий: что это за файл и как им пользоваться" style="min-height:70px"></textarea>
        <button class="btn btn-lime btn-sm mt" style="margin-top:10px" name="action" value="upload_material">Добавить файл</button>
      </form>
    </div>
  </div>

  <!-- MAIN FORM -->
  <form method="post" class="side" style="position:static">
    <?= csrf_field() ?>
    <div class="card">
      <h3>Урок</h3>
      <label class="field mt" style="margin-top:14px">Название<input type="text" name="title" value="<?= e($lesson['title']) ?>" required></label>
      <label class="field">Модуль<select name="module_id"><?php foreach (modules() as $m): ?><option value="<?= $m['id'] ?>" <?= (int)$m['id'] === (int)$lesson['module_id'] ? 'selected' : '' ?>><?= e($m['title']) ?></option><?php endforeach; ?></select></label>
      <label class="field">Ссылка на видео<small>Kinescope, Rutube, VK, YouTube или код iframe</small><input type="text" name="video_url" value="<?= e($lesson['video_url']) ?>" placeholder="https://"></label>
      <label class="field">Конспект урока<small>Строка с «## » в начале — подзаголовок</small><textarea name="body" style="min-height:220px"><?= e($lesson['body']) ?></textarea></label>
    </div>
    <div class="card lime">
      <h3>Домашнее задание</h3>
      <label class="field mt" style="margin-top:14px">Пояснение<textarea name="homework_intro" style="min-height:70px"><?= e($lesson['homework_intro']) ?></textarea></label>
      <div class="field">Графы (каждую вы отметите при проверке)</div>
      <div class="stack-sm">
        <?php foreach ($items as $k => $it): ?>
          <div style="display:grid;grid-template-columns:1fr auto;gap:8px;align-items:center">
            <input type="text" name="item[<?= $it['id'] ?>]" value="<?= e($it['text']) ?>">
            <label class="small" title="Удалить" style="cursor:pointer"><input type="checkbox" name="item_del[<?= $it['id'] ?>]" value="1"> ✕</label>
          </div>
        <?php endforeach; ?>
      </div>
      <label class="field mt" style="margin-top:14px">Новые графы<small>Каждая строка — отдельный пункт</small><textarea name="new_items" style="min-height:70px"></textarea></label>
      <p class="small">Без граф урок открывается без проверки.</p>
    </div>
    <button class="btn btn-blue" name="action" value="save">Сохранить урок</button>
  </form>
</div>
<?php layout_foot();
