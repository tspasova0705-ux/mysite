<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();

/** Swap an item with its neighbour in a positioned list. */
function move_in(string $table, string $scope, int $scopeId, int $id, int $dir): void {
    $where = $scope ? "WHERE $scope=?" : '';
    $rows = all("SELECT id FROM $table $where ORDER BY position, id", $scope ? [$scopeId] : []);
    $ids = array_map(fn($r) => (int)$r['id'], $rows);
    $i = array_search($id, $ids, true);
    $j = $i + $dir;
    if ($i === false || $j < 0 || $j >= count($ids)) return;
    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
    foreach ($ids as $p => $rid) q("UPDATE $table SET position=? WHERE id=?", [$p + 1, $rid]);
}

if (is_post()) {
    csrf_check();
    $a = post('action'); $id = (int)post('id');
    switch ($a) {
        case 'add_module':
            q('INSERT INTO modules(position,title,subtitle,is_free,price_label,pay_url) VALUES((SELECT COALESCE(MAX(position),0)+1 FROM modules),?,?,?,?,?)',
                [post('title') ?: 'Новый модуль', post('subtitle'), isset($_POST['is_free']) ? 1 : 0, post('price_label'), post('pay_url')]);
            flash('Модуль добавлен.'); break;
        case 'save_module':
            q('UPDATE modules SET title=?, subtitle=?, is_free=?, price_label=?, pay_url=? WHERE id=?',
                [post('title') ?: 'Модуль', post('subtitle'), isset($_POST['is_free']) ? 1 : 0, post('price_label'), post('pay_url'), $id]);
            flash('Модуль сохранён.'); break;
        case 'delete_module':
            foreach (all('SELECT f.id FROM files f JOIN lessons l ON l.id=f.lesson_id WHERE l.module_id=?', [$id]) as $f) delete_file((int)$f['id']);
            q('DELETE FROM modules WHERE id=?', [$id]);
            flash('Модуль удалён.'); break;
        case 'move_module': move_in('modules', '', 0, $id, (int)post('dir')); break;
        case 'add_lesson':
            q('INSERT INTO lessons(module_id,position,title,homework_intro) VALUES(?,(SELECT COALESCE(MAX(position),0)+1 FROM lessons WHERE module_id=?),?,?)',
                [$id, $id, post('title') ?: 'Новый урок', 'Выполните каждый пункт и отправьте на проверку. Администратор отметит каждую графу.']);
            redirect('admin/lesson.php?id=' . db()->lastInsertId());
        case 'move_lesson':
            $mid = (int)val('SELECT module_id FROM lessons WHERE id=?', [$id]);
            move_in('lessons', 'module_id', $mid, $id, (int)post('dir')); break;
        case 'delete_lesson':
            $mid = (int)val('SELECT module_id FROM lessons WHERE id=?', [$id]);
            foreach (all('SELECT id FROM files WHERE lesson_id=?', [$id]) as $f) delete_file((int)$f['id']);
            q('DELETE FROM lessons WHERE id=?', [$id]);
            flash('Урок удалён.'); break;
    }
    $anchor = in_array($a, ['move_lesson', 'delete_lesson'], true) ? ($mid ?? 0) : $id;
    redirect('admin/course.php' . ($anchor ? '#m' . $anchor : ''));
}

$mods = modules();
layout_head('Курс', 'course'); ?>
<div class="row between" style="margin:10px 0 18px">
  <h1>Курс</h1>
  <a class="pill" href="#new">+ Модуль</a>
</div>
<p class="muted" style="margin:-6px 0 20px;max-width:700px">Нажмите на урок, чтобы добавить видео, файлы с комментариями и графы домашнего задания. «Бесплатный» модуль открыт всем после регистрации, платный — после того как вы откроете доступ ученику.</p>

<?php foreach ($mods as $i => $m): $lessons = module_lessons((int)$m['id']); ?>
<div class="admin-mod" id="m<?= $m['id'] ?>">
  <div class="admin-mod-head">
    <div class="row">
      <span class="disp" style="font-size:13px;color:var(--blue)">Модуль <?= $i + 1 ?></span>
      <h2 style="font-size:18px"><?= e($m['title']) ?></h2>
      <?= $m['is_free'] ? '<span class="chip chip-free">Бесплатный</span>' : '<span class="chip chip-lock">Платный · ' . (sold_with_course($m) ? 'в составе курса' : e($m['price_label'])) . '</span>' ?>
    </div>
    <form method="post" class="row" style="gap:6px">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>">
      <button class="icon-btn" name="action" value="move_module" onclick="this.form.dir.value=-1" title="Выше">↑</button>
      <button class="icon-btn" name="action" value="move_module" onclick="this.form.dir.value=1" title="Ниже">↓</button>
      <input type="hidden" name="dir" value="0">
    </form>
  </div>

  <div class="admin-lessons">
    <?php foreach ($lessons as $li => $l): ?>
      <div class="admin-lesson">
        <span class="n"><?= $li + 1 ?></span>
        <a href="<?= url('admin/lesson.php?id=' . $l['id']) ?>" style="font-weight:700;text-decoration:none"><?= e($l['title']) ?>
          <span class="small muted" style="font-weight:500"> · <?= hw_count((int)$l['id']) ?> граф<?= ($l['video_url'] || $l['video_file_id']) ? ' · 🎬 видео' : '' ?><?= (int)val("SELECT COUNT(*) FROM files WHERE lesson_id=? AND kind='material'", [$l['id']]) ? ' · 📎 файлы' : '' ?></span></a>
        <form method="post" class="row" style="gap:6px">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $l['id'] ?>"><input type="hidden" name="dir" value="0">
          <button class="icon-btn" name="action" value="move_lesson" onclick="this.form.dir.value=-1" title="Выше">↑</button>
          <button class="icon-btn" name="action" value="move_lesson" onclick="this.form.dir.value=1" title="Ниже">↓</button>
          <a class="btn btn-blue btn-sm" href="<?= url('admin/lesson.php?id=' . $l['id']) ?>">Изменить</a>
        </form>
      </div>
    <?php endforeach; ?>
  </div>

  <form method="post" class="row mt" style="margin-top:12px">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>">
    <input type="text" name="title" placeholder="Название нового урока" style="flex:1;min-width:200px">
    <button class="btn btn-lime btn-sm" name="action" value="add_lesson">+ Урок</button>
  </form>

  <details class="box" style="background:var(--bg);margin:14px 0 0">
    <summary>Настройки модуля</summary>
    <form method="post"><div>
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>">
      <div class="grid2">
        <label class="field">Название<input type="text" name="title" value="<?= e($m['title']) ?>"></label>
        <label class="field">Подзаголовок<input type="text" name="subtitle" value="<?= e($m['subtitle']) ?>"></label>
        <label class="field">Отдельная цена модуля<small>Пусто — модуль входит в полный курс (цена в настройках)</small><input type="text" name="price_label" value="<?= e($m['price_label']) ?>" placeholder="например 9 900 ₽"></label>
        <label class="field">Ссылка на оплату<small>Если пусто — общая ссылка из настроек</small><input type="url" name="pay_url" value="<?= e($m['pay_url']) ?>" placeholder="https://"></label>
      </div>
      <label class="check"><input type="checkbox" name="is_free" value="1" <?= $m['is_free'] ? 'checked' : '' ?>> Бесплатный модуль (открыт всем после регистрации)</label>
      <div class="row between mt">
        <button class="btn btn-blue btn-sm" name="action" value="save_module">Сохранить модуль</button>
        <button class="btn btn-danger btn-sm" name="action" value="delete_module" data-confirm="Удалить модуль со всеми уроками, файлами и ответами учеников?">Удалить модуль</button>
      </div>
    </div></form>
  </details>
</div>
<?php endforeach; ?>

<div class="card lime" id="new">
  <h2>Новый модуль</h2>
  <form method="post" class="mt" style="margin-top:16px">
    <?= csrf_field() ?>
    <div class="grid2">
      <label class="field">Название<input type="text" name="title" required></label>
      <label class="field">Подзаголовок<input type="text" name="subtitle"></label>
      <label class="field">Отдельная цена<small>Пусто — входит в полный курс</small><input type="text" name="price_label" placeholder="например 9 900 ₽"></label>
      <label class="field">Ссылка на оплату<input type="url" name="pay_url" placeholder="https://"></label>
    </div>
    <label class="check"><input type="checkbox" name="is_free" value="1"> Бесплатный</label>
    <button class="btn btn-ink mt" name="action" value="add_module">Добавить модуль</button>
  </form>
</div>
<?php layout_foot();
