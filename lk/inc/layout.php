<?php
function layout_head(string $title, string $active = '', bool $bare = false): void {
    $u = current_user();
    $admin = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/');
    ?><!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> · <?= e(SITE_NAME) ?></title>
<link rel="icon" href="<?= e(BASE) ?>/../assets/logo-mark.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(BASE) ?>/../assets/icon-180.png">
<link rel="stylesheet" href="<?= e(BASE) ?>/../assets/fonts.css">
<link rel="stylesheet" href="<?= url('assets/lk.css') ?>?v=7">
</head>
<body class="<?= $admin ? 'is-admin' : '' ?>">
<?php if (!$bare): ?>
<header class="top">
  <div class="wrap top-in">
    <a href="<?= url($admin ? 'admin/index.php' : 'index.php') ?>" class="logo"><?php logo_html(); ?><?php if ($admin): ?><span class="chip chip-open">админ</span><?php endif; ?></a>
    <?php if ($u): ?>
    <nav class="top-nav">
      <?php if ($admin): ?>
        <?php foreach (['index' => 'Обзор', 'stats' => 'Статистика', 'homework' => 'Домашки', 'messages' => 'Сообщения', 'course' => 'Курс', 'students' => 'Ученики', 'test' => 'Тест', 'settings' => 'Настройки'] as $k => $label): ?>
          <a href="<?= url("admin/$k.php") ?>" class="pill<?= $active === $k ? ' on' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
        <a href="<?= url('index.php') ?>" class="pill ghost">Кабинет ученика</a>
      <?php else: ?>
        <a href="<?= url('index.php') ?>" class="pill<?= $active === 'home' ? ' on' : '' ?>">Мои уроки</a>
        <a href="<?= url('test.php') ?>" class="pill<?= $active === 'test' ? ' on' : '' ?>">Тест и сертификат</a>
        <?php $unread = unread_count((int)$u['id']); ?>
        <a href="<?= url('messages.php') ?>" class="pill<?= $active === 'messages' ? ' on' : '' ?>">Сообщения<?php if ($unread): ?> <span class="badge-n"><?= $unread ?></span><?php endif; ?></a>
        <?php if ($u['role'] === 'admin'): ?><a href="<?= url('admin/index.php') ?>" class="pill blue">Админка</a><?php endif; ?>
      <?php endif; ?>
    </nav>
    <div class="top-user">
      <span class="avatar" title="<?= e($u['name']) ?>"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></span>
      <form method="post" action="<?= url('logout.php') ?>"><?= csrf_field() ?><button class="pill ghost" type="submit">Выйти</button></form>
    </div>
    <?php endif; ?>
  </div>
</header>
<?php endif; ?>
<main class="wrap main">
<?php foreach (take_flash() as [$type, $msg]): ?>
  <div class="flash flash-<?= e($type) ?>"><?= e($msg) ?></div>
<?php endforeach;
}

function layout_foot(): void { ?>
</main>
<footer class="foot wrap">
  <span><?= e(SITE_NAME) ?> · <?= e(OWNER_NAME) ?>, ИНН <?= e(OWNER_INN) ?>, ОГРНИП <?= e(OWNER_OGRNIP) ?></span>
  <span class="foot-links"><?php foreach (CHANNELS as $label => $href): ?><a href="<?= e($href) ?>" target="_blank" rel="noopener"><?= e($label) ?></a><?php endforeach; ?><?php foreach (LEGAL_DOCS as $file => $label): ?><a href="<?= e(BASE) ?>/../<?= $file ?>" target="_blank"><?= $label ?></a><?php endforeach; ?></span>
</footer>
<script src="<?= url('assets/lk.js') ?>?v=2"></script>
</body>
</html>
<?php }

/** Left blue panel used on login / register / setup pages. */
function auth_art(string $title, array $features): void { ?>
  <div class="auth-art">
    <div>
      <a href="<?= e(BASE) ?>/../" class="logo on-blue"><?php logo_html(true); ?></a>
      <div class="big" style="margin-top:28px"><?= $title ?></div>
    </div>
    <div class="feat"><?php foreach ($features as $f): ?><span><?= e($f) ?></span><?php endforeach; ?></div>
    <span class="auth-photo"><img src="<?= e(BASE) ?>/../assets/portrait-cut.webp" alt="Татьяна Марченко — автор курса"></span>
  </div>
<?php }

/** Required consent checkboxes with links to the legal documents. */
function consent_checks(bool $register): void {
    $d = e(BASE) . '/../'; ?>
    <label class="check"><input type="checkbox" name="agree" value="1" required> <span>Принимаю <a href="<?= $d ?>agreement.html" target="_blank">пользовательское соглашение</a> и <a href="<?= $d ?>offer.html" target="_blank">публичную оферту</a></span></label>
    <?php if ($register): ?>
    <label class="check" style="margin-top:10px"><input type="checkbox" name="consent" value="1" required> <span>Даю согласие на обработку персональных данных в соответствии с <a href="<?= $d ?>privacy.html" target="_blank">политикой конфиденциальности</a></span></label>
    <?php endif;
}

/** Brand logo: mark + "partnerka biz" wordmark. */
function logo_html(bool $onBlue = false): void { ?>
<span class="logo-mark"><img src="<?= e(BASE) ?>/../assets/<?= $onBlue ? 'logo-mark-lime.svg' : 'logo-mark.svg' ?>" alt="" width="40" height="40"></span><span class="logo-word">partnerka<b>biz</b></span>
<?php }
