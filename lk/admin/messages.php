<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();

/** Students matching a segment. */
function segment_users(string $seg): array {
    $all = all("SELECT * FROM users WHERE role='student' ORDER BY id");
    if ($seg === 'all') return $all;
    if (str_starts_with($seg, 'user:')) return array_values(array_filter($all, fn($u) => (int)$u['id'] === (int)substr($seg, 5)));
    if ($seg === 'inactive') return array_values(array_filter($all, fn($u) => !$u['last_seen'] || strtotime($u['last_seen']) < time() - 7 * 86400));
    return array_values(array_filter($all, fn($u) => student_stage($u) === $seg));
}

if (is_post()) {
    csrf_check();
    if (post('action') === 'send') {
        $users = segment_users(post('segment'));
        $title = mb_substr(post('title'), 0, 120); $body = mb_substr(post('body'), 0, 5000);
        if (!$users || $body === '') flash('Некому отправить или пустой текст.', 'err');
        else {
            foreach ($users as $u) notify((int)$u['id'], $title ?: 'Сообщение от автора курса', $body, post('link'), isset($_POST['email']));
            flash('Отправлено: ' . count($users) . ' ' . plural(count($users), 'ученику', 'ученикам', 'ученикам') . '.');
        }
    } elseif (post('action') === 'reply') {
        $q = one('SELECT * FROM messages WHERE id=? AND from_admin=0', [(int)post('id')]);
        if ($q && post('body') !== '') {
            notify((int)$q['user_id'], 'Ответ на ваш вопрос', post('body'), '', isset($_POST['email']));
            q('UPDATE messages SET read_at=? WHERE id=?', [now(), $q['id']]);
            flash('Ответ отправлен.');
        }
    } elseif (post('action') === 'done') {
        q('UPDATE messages SET read_at=? WHERE id=? AND from_admin=0', [now(), (int)post('id')]);
    }
    redirect('admin/messages.php');
}

$questions = all("SELECT m.*, u.name, u.email FROM messages m JOIN users u ON u.id=m.user_id WHERE m.from_admin=0 ORDER BY (m.read_at IS NULL) DESC, m.id DESC LIMIT 100");
$counts = ['all' => count(segment_users('all')), 'inactive' => count(segment_users('inactive'))];
foreach (STAGES as $k => $_) $counts[$k] = count(segment_users($k));
$students = all("SELECT id,name,email FROM users WHERE role='student' ORDER BY name");
$sent = all("SELECT title, body, MIN(created_at) AS at, COUNT(*) AS n, SUM(read_at IS NOT NULL) AS r FROM messages WHERE from_admin=1 GROUP BY title, body, substr(created_at,1,16) ORDER BY at DESC LIMIT 15");
$pre = (string)($_GET['to'] ?? '');

layout_head('Сообщения', 'messages'); ?>
<h1 style="margin:10px 0 18px">Сообщения</h1>
<div class="grid2" style="align-items:start">
  <form method="post" class="card lime">
    <?= csrf_field() ?><input type="hidden" name="action" value="send">
    <h2>Новое сообщение</h2>
    <p class="small" style="margin:8px 0 16px">Появится у учеников в кабинете в разделе «Сообщения» с красным счётчиком в меню. Можно продублировать на почту.</p>
    <label class="field">Кому<select name="segment">
      <option value="all">Всем ученикам (<?= $counts['all'] ?>)</option>
      <?php foreach (STAGES as $k => $label): ?><option value="<?= $k ?>"><?= e($label) ?> (<?= $counts[$k] ?>)</option><?php endforeach; ?>
      <option value="inactive">Не заходили больше 7 дней (<?= $counts['inactive'] ?>)</option>
      <optgroup label="Одному ученику">
        <?php foreach ($students as $s): ?><option value="user:<?= $s['id'] ?>" <?= $pre === 'user:' . $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?> · <?= e($s['email']) ?></option><?php endforeach; ?>
      </optgroup>
    </select></label>
    <label class="field">Заголовок<input type="text" name="title" maxlength="120" placeholder="Например: Новый бонус в курсе"></label>
    <label class="field">Текст<small>Ссылки в тексте станут кликабельными</small><textarea name="body" required style="min-height:140px"></textarea></label>
    <label class="field">Кнопка-ссылка (необязательно)<input type="text" name="link" placeholder="https://… или ссылка на урок"></label>
    <label class="check"><input type="checkbox" name="email" value="1"> Продублировать на почту</label>
    <button class="btn btn-ink mt">Отправить</button>
  </form>
  <div>
    <h2 style="margin-bottom:12px">Вопросы учеников</h2>
    <?php if (!$questions): ?><div class="empty">Вопросов пока нет</div><?php endif; ?>
    <?php foreach ($questions as $q): ?>
      <div class="msg <?= $q['read_at'] ? '' : 'new' ?>" style="margin-bottom:10px">
        <div class="msg-head"><b><?= e($q['name']) ?> <span class="muted small" style="font-weight:500"><?= e($q['email']) ?></span></b><span><?= e(date('d.m H:i', strtotime($q['created_at']))) ?></span></div>
        <div class="msg-body"><?= nl2br(linkify($q['body'])) ?></div>
        <?php if (!$q['read_at']): ?>
        <form method="post" style="margin-top:12px">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $q['id'] ?>">
          <textarea name="body" placeholder="Ответ" style="min-height:70px"></textarea>
          <div class="row" style="margin-top:8px">
            <button class="btn btn-blue btn-sm" name="action" value="reply">Ответить</button>
            <label class="check"><input type="checkbox" name="email" value="1"> + на почту</label>
            <button class="btn btn-line btn-sm" name="action" value="done" formnovalidate>Отметить решённым</button>
          </div>
        </form>
        <?php else: ?><span class="chip chip-done" style="margin-top:8px">Отвечено</span><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($sent): ?>
    <h2 class="mt" style="margin-bottom:12px">Отправленные</h2>
    <div class="table-wrap"><table class="t"><tbody>
      <?php foreach ($sent as $m): ?><tr><td><b><?= e($m['title']) ?></b><div class="small muted"><?= e(mb_strimwidth($m['body'], 0, 90, '…')) ?></div></td><td class="small"><?= (int)$m['r'] ?>/<?= (int)$m['n'] ?> прочитали</td><td class="small muted"><?= e(date('d.m H:i', strtotime($m['at']))) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </div>
</div>
<?php layout_foot();
