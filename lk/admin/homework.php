<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();

$filters = ['submitted' => 'На проверке', 'returned' => 'На доработке', 'accepted' => 'Принятые', 'all' => 'Все'];
$f = array_key_exists($_GET['f'] ?? '', $filters) ? $_GET['f'] : 'submitted';
$where = $f === 'all' ? '1=1' : 's.status=?';
$rows = all("SELECT s.*, u.name, u.email, l.title, m.title AS mtitle,
    (SELECT COUNT(*) FROM answers a WHERE a.submission_id=s.id AND a.mark='done') AS done_n,
    (SELECT COUNT(*) FROM hw_items h WHERE h.lesson_id=s.lesson_id) AS items_n
    FROM submissions s JOIN users u ON u.id=s.user_id JOIN lessons l ON l.id=s.lesson_id JOIN modules m ON m.id=l.module_id
    WHERE $where ORDER BY s.updated_at " . ($f === 'submitted' ? 'ASC' : 'DESC') . ' LIMIT 300', $f === 'all' ? [] : [$f]);

layout_head('Домашние задания', 'homework'); ?>
<div class="row between" style="margin:10px 0 18px"><h1>Домашние задания</h1></div>
<div class="tabs-filter">
  <?php foreach ($filters as $k => $label): ?><a class="pill<?= $f === $k ? ' on' : '' ?>" href="?f=<?= $k ?>"><?= $label ?></a><?php endforeach; ?>
</div>
<?php if (!$rows): ?><div class="empty">Здесь пока пусто</div><?php else: ?>
<div class="table-wrap"><table class="t">
  <thead><tr><th>Ученик</th><th>Урок</th><th>Графы</th><th>Статус</th><th>Обновлено</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['name']) ?></b><div class="small muted"><?= e($r['email']) ?></div></td>
      <td><?= e($r['title']) ?><div class="small muted"><?= e($r['mtitle']) ?></div></td>
      <td class="small"><b><?= (int)$r['done_n'] ?></b> / <?= (int)$r['items_n'] ?></td>
      <td><?= state_chip($r['status']) ?></td>
      <td class="small muted"><?= e(date('d.m.Y H:i', strtotime($r['updated_at']))) ?></td>
      <td><div class="acts"><a class="btn btn-<?= $r['status'] === 'submitted' ? 'blue' : 'line' ?> btn-sm" href="<?= url('admin/review.php?id=' . $r['id']) ?>"><?= $r['status'] === 'submitted' ? 'Проверить' : 'Открыть' ?></a></div></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php layout_foot();
