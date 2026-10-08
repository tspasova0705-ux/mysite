<?php
require __DIR__ . '/inc/bootstrap.php';
// Public page: anyone with the link can verify the certificate.
$c = one('SELECT c.*, u.name FROM certificates c JOIN users u ON u.id=c.user_id WHERE c.code=?', [(string)($_GET['c'] ?? '')]);
if (!$c) { http_response_code(404); layout_head('Сертификат не найден', '', true); echo '<div class="empty mt">Сертификат с таким номером не найден.</div>'; layout_foot(); exit; }
$months = ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];
$t = strtotime($c['issued_at']);
$date = date('j', $t) . ' ' . $months[(int)date('n', $t) - 1] . ' ' . date('Y', $t);
$mine = current_user() && (int)current_user()['id'] === (int)$c['user_id'];

layout_head('Сертификат', 'test', !current_user()); ?>
<div class="row between no-print" style="margin:8px 0 4px">
  <?php if (current_user()): ?><a class="pill" href="<?= url('index.php') ?>">← В кабинет</a><?php else: ?><span class="chip chip-done">Сертификат подлинный</span><?php endif; ?>
  <?php if ($mine): ?><button class="btn btn-blue btn-sm" onclick="window.print()">Скачать PDF / распечатать</button><?php endif; ?>
</div>
<div class="cert-wrap">
  <div class="cert">
    <span class="brand"><?= e(SITE_NAME) ?></span>
    <div>
      <div class="ttl">Серти<span>фикат</span></div>
      <div class="who">подтверждает, что</div>
      <div class="name"><?= e($c['name']) ?></div>
      <div class="course">успешно прошёл(ла) онлайн-курс «<?= e(COURSE_NAME) ?>», выполнил(а) все практические задания и итоговый тест</div>
    </div>
    <div class="meta">
      <div><b><?= e($date) ?></b>дата выдачи</div>
      <div><b>№ <?= e($c['code']) ?></b>номер сертификата</div>
      <div><b><?= (int)$c['score'] ?>%</b>результат теста</div>
    </div>
    <div class="seal"><div><b>✓</b>курс<br>пройден</div></div>
  </div>
</div>
<?php if ($mine): ?><p class="small muted no-print" style="text-align:center">В окне печати выберите «Сохранить как PDF» и альбомную ориентацию. Ссылкой на эту страницу можно поделиться — по ней проверяется подлинность.</p><?php endif; ?>
<?php layout_foot();
