<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();

$students = (int)val("SELECT COUNT(*) FROM users WHERE role='student'");
$pending = all("SELECT s.*, u.name, u.email, l.title FROM submissions s JOIN users u ON u.id=s.user_id JOIN lessons l ON l.id=s.lesson_id WHERE s.status='submitted' ORDER BY s.updated_at LIMIT 8");
$pendingCount = (int)val("SELECT COUNT(*) FROM submissions WHERE status='submitted'");
$payments = all("SELECT p.*, u.name, u.email, m.title FROM payment_requests p JOIN users u ON u.id=p.user_id JOIN modules m ON m.id=p.module_id WHERE p.status='new' ORDER BY p.id");
$certs = (int)val('SELECT COUNT(*) FROM certificates');
$autoPaid = all("SELECT p.*, u.name, u.email FROM payment_requests p JOIN users u ON u.id=p.user_id WHERE p.status='auto' ORDER BY p.id DESC LIMIT 10");
$recent = all("SELECT * FROM users WHERE role='student' ORDER BY id DESC LIMIT 5");

layout_head('Админка', 'index'); ?>

<section class="hello reveal">
  <div>
    <span class="pill" style="border-color:rgba(255,255,255,.5);color:#fff">Панель администратора</span>
    <h1 style="margin-top:20px">Всё под <em>контролем</em></h1>
    <p>Проверяйте домашние задания, открывайте доступ после оплаты и редактируйте уроки.</p>
    <a class="btn btn-lime" href="<?= url('admin/homework.php') ?>">Проверить задания<?= $pendingCount ? " ($pendingCount)" : '' ?></a>
  </div>
</section>

<div class="stats">
  <div class="stat lime"><b><?= $pendingCount ?></b><span>заданий ждут проверки</span></div>
  <div class="stat"><b><?= count($payments) ?></b><span>заявок на оплату</span></div>
  <div class="stat"><b><?= $students ?></b><span>учеников</span></div>
  <div class="stat"><b><?= $certs ?></b><span>сертификатов выдано</span></div>
</div>

<div class="grid2 mt">
  <div>
    <div class="row between" style="margin-bottom:12px"><h2>На проверке</h2><a class="pill" href="<?= url('admin/homework.php') ?>">Все</a></div>
    <?php if (!$pending): ?><div class="empty">Новых заданий нет 🎉</div><?php else: ?>
    <div class="table-wrap"><table class="t"><tbody>
      <?php foreach ($pending as $s): ?>
        <tr><td><b><?= e($s['name']) ?></b><div class="small muted"><?= e($s['title']) ?></div></td>
        <td class="small muted"><?= e(date('d.m H:i', strtotime($s['updated_at']))) ?></td>
        <td><div class="acts"><a class="btn btn-blue btn-sm" href="<?= url('admin/review.php?id=' . $s['id']) ?>">Проверить</a></div></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </div>
  <div>
    <div class="row between" style="margin-bottom:12px"><h2>Заявки на оплату</h2><a class="pill" href="<?= url('admin/students.php') ?>">Ученики</a></div>
    <?php if (!$payments): ?><div class="empty">Новых заявок нет</div><?php else: ?>
    <div class="table-wrap"><table class="t"><tbody>
      <?php foreach ($payments as $p): ?>
        <tr><td><b><?= e($p['name']) ?></b><div class="small muted"><?= e($p['email']) ?> · <?= e($p['title']) ?></div><?php if ($p['note']): ?><div class="small">«<?= e($p['note']) ?>»</div><?php endif; ?></td>
        <td><form method="post" action="<?= url('admin/students.php') ?>" class="acts">
          <?= csrf_field() ?><input type="hidden" name="req" value="<?= $p['id'] ?>">
          <button class="btn btn-lime btn-sm" name="action" value="approve_req">Открыть доступ</button>
          <button class="btn btn-line btn-sm" name="action" value="reject_req" data-confirm="Отклонить заявку?">✕</button>
        </form></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    <?php if ($autoPaid): ?>
    <h2 class="mt" style="margin-bottom:6px">Оплаты GetPlatinum</h2>
    <p class="small muted" style="margin-bottom:12px">Курс открыт автоматически после возврата со страницы оплаты. Сверьте с платежами в GetPlatinum; если оплаты нет — закройте доступ в «Учениках».</p>
    <div class="table-wrap"><table class="t"><tbody>
      <?php foreach ($autoPaid as $p): ?>
        <tr><td><b><?= e($p['name']) ?></b><div class="small muted"><?= e($p['email']) ?></div></td><td class="small muted"><?= e(date('d.m H:i', strtotime($p['created_at']))) ?></td><td><div class="acts"><a class="btn btn-line btn-sm" href="<?= url('admin/students.php?u=' . $p['user_id'] . '#u' . $p['user_id']) ?>">Ученик</a></div></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    <h2 class="mt" style="margin-bottom:12px">Новые ученики</h2>
    <?php if (!$recent): ?><div class="empty">Пока никого — поделитесь ссылкой на регистрацию:<br><b><?= e((empty($_SERVER['HTTPS']) ? 'http' : 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . url('register.php')) ?></b></div><?php else: ?>
    <div class="table-wrap"><table class="t"><tbody>
      <?php foreach ($recent as $r): [$d, $tt, $pc] = progress($r); ?>
        <tr><td><b><?= e($r['name']) ?></b><div class="small muted"><?= e($r['email']) ?></div></td><td class="small"><?= $pc ?>%</td><td class="small muted"><?= e(date('d.m.Y', strtotime($r['created_at']))) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </div>
</div>
<?php layout_foot();
