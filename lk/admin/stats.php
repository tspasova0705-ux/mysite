<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();

$students = all("SELECT * FROM users WHERE role='student' ORDER BY id DESC");
$total = count($students);
$byStage = array_fill_keys(array_keys(STAGES), []);
foreach ($students as $u) $byStage[student_stage($u)][] = $u;
$reached = []; $keys = array_keys(STAGES); // how many reached at least each stage
foreach ($keys as $i => $k) { $n = 0; foreach (array_slice($keys, $i) as $kk) $n += count($byStage[$kk]); $reached[$k] = $n; }

$days = 30; $reg = [];
for ($i = $days - 1; $i >= 0; $i--) $reg[date('Y-m-d', strtotime("-$i day"))] = 0;
foreach (all("SELECT substr(created_at,1,10) d, COUNT(*) n FROM users WHERE role='student' AND created_at >= ? GROUP BY d", [date('Y-m-d', strtotime('-' . ($days - 1) . ' day'))]) as $r) $reg[$r['d']] = (int)$r['n'];
$maxReg = max(1, max($reg));
$active7 = count(array_filter($students, fn($u) => $u['last_seen'] && strtotime($u['last_seen']) >= time() - 7 * 86400));
$paid = $reached['paid'];
$conv = $reached['free_done'] ? round($paid * 100 / $reached['free_done']) : 0;

// where students are now: the lesson each one is working on
$lessonLoad = [];
foreach ($students as $u) {
    foreach (course_lessons() as $l) {
        $st = lesson_state($u, $l);
        if (in_array($st, ['open', 'returned', 'submitted'], true)) { $lessonLoad[$l['id']] = ($lessonLoad[$l['id']] ?? 0) + 1; break; }
    }
}
$titles = array_column(course_lessons(), 'title', 'id');
arsort($lessonLoad);

layout_head('Статистика', 'stats'); ?>
<h1 style="margin:10px 0 18px">Статистика</h1>
<div class="stats" style="margin-top:0">
  <div class="stat lime"><b><?= $total ?></b><span>всего зарегистрировалось</span></div>
  <div class="stat"><b><?= array_sum(array_slice($reg, -7)) ?></b><span>за последние 7 дней</span></div>
  <div class="stat"><b><?= $active7 ?></b><span>заходили за неделю</span></div>
  <div class="stat"><b><?= $paid ?> · <?= $conv ?>%</b><span>оплатили · из прошедших бесплатный модуль</span></div>
</div>

<div class="card mt">
  <h2>Регистрации за 30 дней</h2>
  <div class="chart"><?php foreach ($reg as $d => $n): ?><div style="height:<?= max(1.5, $n * 100 / $maxReg) ?>%" title="<?= e(date('d.m', strtotime($d))) ?>: <?= $n ?>"><span><?= $n ?></span></div><?php endforeach; ?></div>
  <div class="chart-x"><span><?= e(date('d.m', strtotime(array_key_first($reg)))) ?></span><span>сегодня</span></div>
</div>

<div class="grid2 mt" style="align-items:start">
  <div class="card">
    <h2>Воронка</h2>
    <p class="small muted" style="margin:6px 0 16px">Сколько учеников дошли до этапа или дальше</p>
    <div class="funnel">
      <?php foreach (FUNNEL as $k => $label): ?>
        <div class="frow"><span class="small"><?= e($label) ?></span><span class="fbar"><i style="width:<?= $total ? $reached[$k] * 100 / $total : 0 ?>%"></i></span><b><?= $reached[$k] ?></b></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <h2>На каком уроке сейчас</h2>
    <p class="small muted" style="margin:6px 0 16px">Урок, который ученик проходит или ждёт проверки</p>
    <?php if (!$lessonLoad): ?><p class="muted">Пока никого</p><?php endif; ?>
    <div class="funnel">
      <?php $mx = max([1] + $lessonLoad); foreach ($lessonLoad as $lid => $n): ?>
        <div class="frow"><span class="small"><?= e($titles[$lid] ?? '') ?></span><span class="fbar"><i style="width:<?= $n * 100 / $mx ?>%;background:var(--ink)"></i></span><b><?= $n ?></b></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<h2 class="mt" style="margin-bottom:12px">Ученики по этапам</h2>
<?php foreach (STAGES as $k => $label): if (!$byStage[$k]) continue; ?>
<details class="box">
  <summary><?= e($label) ?> · <?= count($byStage[$k]) ?></summary>
  <div><div class="table-wrap"><table class="t"><tbody>
    <?php foreach ($byStage[$k] as $u): [$d, $t, $pc] = progress($u); ?>
      <tr>
        <td><b><?= e($u['name']) ?></b><div class="small muted"><?= e($u['email']) ?></div></td>
        <td class="small"><?= $pc ?>% · <?= $d ?>/<?= $t ?></td>
        <td class="small muted">рег. <?= e(date('d.m.Y', strtotime($u['created_at']))) ?><br>был(а): <?= $u['last_seen'] ? e(date('d.m H:i', strtotime($u['last_seen']))) : '—' ?></td>
        <td><div class="acts"><a class="btn btn-line btn-sm" href="<?= url('admin/messages.php?to=user:' . $u['id']) ?>">Написать</a><a class="btn btn-line btn-sm" href="<?= url('admin/students.php?u=' . $u['id'] . '#u' . $u['id']) ?>">Карточка</a></div></td>
      </tr>
    <?php endforeach; ?>
  </tbody></table></div></div>
</details>
<?php endforeach; ?>
<?php layout_foot();
