<?php
require __DIR__ . '/../inc/bootstrap.php';
$me = require_admin();

if (is_post()) {
    csrf_check();
    $uid = (int)post('uid');
    switch (post('action')) {
        case 'approve_req':
            $r = one('SELECT * FROM payment_requests WHERE id=?', [(int)post('req')]);
            if ($r) {
                q('INSERT OR IGNORE INTO access(user_id,module_id,granted_at) VALUES(?,?,?)', [$r['user_id'], $r['module_id'], now()]);
                q("UPDATE payment_requests SET status='approved' WHERE id=?", [$r['id']]);
                flash('Доступ открыт.');
            }
            redirect('admin/index.php');
        case 'reject_req':
            q("UPDATE payment_requests SET status='rejected' WHERE id=?", [(int)post('req')]);
            flash('Заявка отклонена.');
            redirect('admin/index.php');
        case 'access':
            $mid = (int)post('module');
            if (post('on') === '1') {
                q('INSERT OR IGNORE INTO access(user_id,module_id,granted_at) VALUES(?,?,?)', [$uid, $mid, now()]);
                q("UPDATE payment_requests SET status='approved' WHERE user_id=? AND module_id=? AND status='new'", [$uid, $mid]);
            } else q('DELETE FROM access WHERE user_id=? AND module_id=?', [$uid, $mid]);
            break;
        case 'grant_all':
            foreach (all('SELECT id FROM modules WHERE is_free=0') as $m) q('INSERT OR IGNORE INTO access(user_id,module_id,granted_at) VALUES(?,?,?)', [$uid, $m['id'], now()]);
            q("UPDATE payment_requests SET status='approved' WHERE user_id=? AND status='new'", [$uid]);
            flash('Открыт доступ ко всем платным модулям.');
            break;
        case 'password':
            $p = post('password');
            if (mb_strlen($p) < 6) flash('Пароль — минимум 6 символов.', 'err');
            else { q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($p, PASSWORD_DEFAULT), $uid]); flash('Новый пароль сохранён — передайте его ученику.'); }
            break;
        case 'delete':
            if ($uid !== (int)$me['id']) {
                foreach (all("SELECT id FROM files WHERE user_id=?", [$uid]) as $f) delete_file((int)$f['id']);
                q("DELETE FROM users WHERE id=? AND role='student'", [$uid]);
                flash('Ученик удалён.');
            }
            redirect('admin/students.php');
    }
    redirect('admin/students.php?u=' . $uid);
}

$paid = all('SELECT * FROM modules WHERE is_free=0 ORDER BY position, id');
$search = trim((string)($_GET['s'] ?? ''));
$students = $search !== ''
    ? all("SELECT * FROM users WHERE role='student' AND (name LIKE ? OR email LIKE ?) ORDER BY id DESC", ["%$search%", "%$search%"])
    : all("SELECT * FROM users WHERE role='student' ORDER BY id DESC");
$open = get_int('u');

layout_head('Ученики', 'students'); ?>
<div class="row between" style="margin:10px 0 18px">
  <h1>Ученики <span class="muted" style="font-size:.5em"><?= count($students) ?></span></h1>
  <form class="row"><input type="text" name="s" value="<?= e($search) ?>" placeholder="Поиск по имени или почте" style="width:260px"><button class="pill">Найти</button></form>
</div>
<?php if (!$students): ?><div class="empty">Учеников пока нет</div><?php endif; ?>
<?php foreach ($students as $s):
    [$d, $t, $pc] = progress($s);
    $cert = one('SELECT code FROM certificates WHERE user_id=?', [$s['id']]);
    $reqs = all("SELECT module_id, note FROM payment_requests WHERE user_id=? AND status='new'", [$s['id']]);
    $reqMods = array_column($reqs, 'module_id');
?>
<details class="box" id="u<?= $s['id'] ?>" <?= $open === (int)$s['id'] ? 'open' : '' ?>>
  <summary>
    <span class="row between" style="width:100%">
      <span><?= e($s['name']) ?> <span class="muted" style="text-transform:none;font-family:Manrope;font-weight:600"> · <?= e($s['email']) ?></span></span>
      <span class="row" style="gap:6px"><?= $reqs ? '<span class="chip chip-wait">Заявка на оплату</span>' : '' ?><?= $cert ? '<span class="chip chip-done">Сертификат</span>' : '' ?><span class="chip chip-open"><?= $pc ?>%</span></span>
    </span>
  </summary>
  <div>
    <div class="grid3">
      <div class="stat"><b><?= $d ?>/<?= $t ?></b><span>заданий принято</span></div>
      <div class="stat"><b><?= (int)val("SELECT COUNT(*) FROM submissions WHERE user_id=? AND status='submitted'", [$s['id']]) ?></b><span>на проверке</span></div>
      <div class="stat"><b><?= e(date('d.m.Y', strtotime($s['created_at']))) ?></b><span>дата регистрации</span></div>
    </div>
    <h3 class="mt">Доступ к платным модулям</h3>
    <div class="admin-lessons">
      <?php foreach ($paid as $m): $has = (bool)val('SELECT 1 FROM access WHERE user_id=? AND module_id=?', [$s['id'], $m['id']]); ?>
        <form method="post" class="admin-lesson" style="grid-template-columns:1fr auto">
          <?= csrf_field() ?><input type="hidden" name="uid" value="<?= $s['id'] ?>"><input type="hidden" name="module" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="access">
          <span><b><?= e($m['title']) ?></b> <?= in_array($m['id'], $reqMods) ? '<span class="chip chip-wait">сообщил(а) об оплате</span>' : '' ?></span>
          <?php if ($has): ?><button class="btn btn-line btn-sm" name="on" value="0" data-confirm="Закрыть доступ к модулю?">✓ Открыт · закрыть</button>
          <?php else: ?><button class="btn btn-lime btn-sm" name="on" value="1">Открыть доступ</button><?php endif; ?>
        </form>
      <?php endforeach; ?>
    </div>
    <?php foreach ($reqs as $r): if ($r['note']): ?><p class="small mt" style="margin-top:8px">Комментарий к оплате: «<?= e($r['note']) ?>»</p><?php endif; endforeach; ?>
    <div class="row mt">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="uid" value="<?= $s['id'] ?>"><button class="btn btn-blue btn-sm" name="action" value="grant_all">Открыть весь курс</button></form>
      <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="uid" value="<?= $s['id'] ?>"><input type="text" name="password" placeholder="Новый пароль" style="width:180px"><button class="btn btn-line btn-sm" name="action" value="password">Сменить пароль</button></form>
      <?php if ($cert): ?><a class="btn btn-line btn-sm" href="<?= url('certificate.php?c=' . $cert['code']) ?>" target="_blank">Сертификат</a><?php endif; ?>
      <a class="btn btn-line btn-sm" href="<?= url('admin/homework.php?f=all') ?>">Задания</a>
      <form method="post" style="margin-left:auto"><?= csrf_field() ?><input type="hidden" name="uid" value="<?= $s['id'] ?>"><button class="btn btn-danger btn-sm" name="action" value="delete" data-confirm="Удалить ученика и все его ответы?">Удалить</button></form>
    </div>
  </div>
</details>
<?php endforeach; ?>
<?php layout_foot();
