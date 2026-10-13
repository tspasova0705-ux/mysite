<?php
require __DIR__ . '/inc/bootstrap.php';
$user = require_login();
if ($user['role'] === 'admin') redirect('admin/messages.php');

if (is_post()) {
    csrf_check();
    $text = mb_substr(post('body'), 0, 3000);
    if (mb_strlen($text) < 2) flash('Напишите вопрос.', 'err');
    else {
        q('INSERT INTO messages(user_id,from_admin,title,body,created_at) VALUES(?,0,?,?,?)', [$user['id'], 'Вопрос ученика', $text, now()]);
        flash('Вопрос отправлен. Ответ придёт сюда, в «Сообщения».');
    }
    redirect('messages.php');
}

$list = all('SELECT * FROM messages WHERE user_id=? ORDER BY id DESC LIMIT 200', [$user['id']]);
q('UPDATE messages SET read_at=? WHERE user_id=? AND from_admin=1 AND read_at IS NULL', [now(), $user['id']]);

layout_head('Сообщения', 'messages'); ?>
<div class="row between" style="margin:10px 0 18px"><h1>Сообщения</h1></div>
<div class="lesson-grid">
  <div class="msgs">
    <?php if (!$list): ?><div class="empty">Здесь будут уведомления о проверке домашек, новости курса и ответы на ваши вопросы.</div><?php endif; ?>
    <?php foreach ($list as $m): ?>
      <div class="msg <?= $m['from_admin'] ? 'in' : 'out' ?><?= $m['from_admin'] && !$m['read_at'] ? ' new' : '' ?>">
        <div class="msg-head"><b><?= $m['from_admin'] ? e($m['title'] ?: 'Сообщение') : 'Ваш вопрос' ?></b><span><?= e(date('d.m.Y H:i', strtotime($m['created_at']))) ?></span></div>
        <div class="msg-body"><?= nl2br(linkify($m['body'])) ?></div>
        <?php if ($m['link']): ?><a class="btn btn-blue btn-sm" style="margin-top:12px" href="<?= e($m['link']) ?>">Открыть</a><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <aside class="side">
    <form method="post" class="card lime">
      <?= csrf_field() ?>
      <h3>Задать вопрос</h3>
      <p class="small" style="margin:8px 0 12px">Вопрос получит автор курса, ответ появится здесь.</p>
      <textarea name="body" required maxlength="3000" placeholder="Ваш вопрос"></textarea>
      <button class="btn btn-ink" style="margin-top:12px;width:100%">Отправить</button>
    </form>
  </aside>
</div>
<?php layout_foot();
