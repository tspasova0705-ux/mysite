<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();

if (is_post()) {
    csrf_check();
    $id = (int)post('id');
    $type = post('type') === 'open' ? 'open' : 'single';
    $opts = array_values(array_filter(array_map('trim', preg_split('~\R~', (string)($_POST['options'] ?? ''))), 'strlen'));
    $correct = max(0, (int)post('correct') - 1);
    switch (post('action')) {
        case 'add':
            q('INSERT INTO test_questions(position,text,type,options,correct) VALUES((SELECT COALESCE(MAX(position),0)+1 FROM test_questions),?,?,?,?)',
                [post('text'), $type, json_encode($opts, JSON_UNESCAPED_UNICODE), $correct]);
            flash('Вопрос добавлен.'); break;
        case 'save':
            q('UPDATE test_questions SET text=?, type=?, options=?, correct=? WHERE id=?', [post('text'), $type, json_encode($opts, JSON_UNESCAPED_UNICODE), $correct, $id]);
            flash('Вопрос сохранён.'); break;
        case 'delete':
            q('DELETE FROM test_questions WHERE id=?', [$id]);
            flash('Вопрос удалён.'); break;
    }
    redirect('admin/test.php');
}

$questions = all('SELECT * FROM test_questions ORDER BY position, id');
$attempts = all('SELECT a.*, u.name, u.email FROM test_attempts a JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 100');
$qById = array_column($questions, null, 'id');

function question_form(?array $q): void {
    $opts = $q ? (json_decode($q['options'], true) ?: []) : []; ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $q['id'] ?? 0 ?>">
      <label class="field">Вопрос<input type="text" name="text" value="<?= e($q['text'] ?? '') ?>" required></label>
      <div class="grid2">
        <label class="field">Тип<select name="type"><option value="single">С вариантами ответа</option><option value="open" <?= ($q['type'] ?? '') === 'open' ? 'selected' : '' ?>>Открытый (обратная связь)</option></select></label>
        <label class="field">Номер правильного варианта<input type="number" name="correct" min="1" value="<?= ($q['correct'] ?? 0) + 1 ?>"></label>
      </div>
      <label class="field">Варианты ответа<small>Каждый с новой строки. Для открытого вопроса не нужны.</small><textarea name="options" style="min-height:100px"><?= e(implode("\n", $opts)) ?></textarea></label>
      <div class="row between">
        <button class="btn btn-blue btn-sm" name="action" value="<?= $q ? 'save' : 'add' ?>"><?= $q ? 'Сохранить' : 'Добавить вопрос' ?></button>
        <?php if ($q): ?><button class="btn btn-danger btn-sm" name="action" value="delete" data-confirm="Удалить вопрос?">Удалить</button><?php endif; ?>
      </div>
    </form>
<?php }

layout_head('Итоговый тест', 'test'); ?>
<div class="row between" style="margin:10px 0 18px"><h1>Итоговый тест</h1><a class="pill" href="<?= url('admin/settings.php') ?>">Проходной балл: <?= e(setting('pass_percent', '70')) ?>%</a></div>

<div class="grid2" style="align-items:start">
  <div>
    <h2 style="margin-bottom:12px">Вопросы</h2>
    <?php foreach ($questions as $i => $q): ?>
      <details class="box">
        <summary><?= $i + 1 ?>. <?= e(mb_strimwidth($q['text'], 0, 70, '…')) ?> <?= $q['type'] === 'open' ? '<span class="chip chip-wait">открытый</span>' : '' ?></summary>
        <div><?php question_form($q); ?></div>
      </details>
    <?php endforeach; ?>
    <div class="card lime"><h3 style="margin-bottom:14px">Новый вопрос</h3><?php question_form(null); ?></div>
  </div>
  <div>
    <h2 style="margin-bottom:12px">Ответы учеников</h2>
    <?php if (!$attempts): ?><div class="empty">Попыток пока нет</div><?php endif; ?>
    <?php foreach ($attempts as $a): $ans = json_decode($a['answers'], true) ?: []; $pct = (int)round($a['score'] * 100 / max(1, $a['total'])); ?>
      <details class="box">
        <summary><span class="row between" style="width:100%"><span><?= e($a['name']) ?></span><span class="chip <?= $a['passed'] ? 'chip-done' : 'chip-redo' ?>"><?= $pct ?>%</span></span></summary>
        <div>
          <p class="small muted"><?= e($a['email']) ?> · <?= e(date('d.m.Y H:i', strtotime($a['created_at']))) ?> · <?= (int)$a['score'] ?> из <?= (int)$a['total'] ?></p>
          <?php foreach ($ans as $qid => $v): $q = $qById[$qid] ?? null; if (!$q || $q['type'] !== 'open') continue; ?>
            <div class="hw-item" style="background:var(--bg);margin-top:10px"><b class="small"><?= e($q['text']) ?></b><div class="hw-answer" style="background:#fff"><?= e((string)$v) ?></div></div>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
</div>
<?php layout_foot();
