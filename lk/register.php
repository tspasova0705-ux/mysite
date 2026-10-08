<?php
require __DIR__ . '/inc/bootstrap.php';
if (!admin_exists()) redirect('setup.php');
if (current_user()) redirect('index.php');

$error = '';
if (is_post()) {
    csrf_check();
    $name = post('name'); $email = mb_strtolower(post('email')); $pass = post('password');
    if (mb_strlen($name) < 2) $error = 'Укажите имя.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Проверьте почту.';
    elseif (mb_strlen($pass) < 6) $error = 'Пароль — минимум 6 символов.';
    elseif (empty($_POST['agree']) || empty($_POST['consent'])) $error = 'Отметьте согласие с документами — без него регистрация невозможна.';
    elseif (val('SELECT 1 FROM users WHERE email=?', [$email])) $error = 'Такая почта уже зарегистрирована — войдите.';
    else {
        q("INSERT INTO users(email,name,password_hash,role,created_at,consent_at) VALUES(?,?,?,'student',?,?)", [$email, $name, password_hash($pass, PASSWORD_DEFAULT), now(), now()]);
        login_user((int)db()->lastInsertId());
        // came from "Купить курс" on the landing -> straight to payment
        $paid = !empty($_POST['buy']) ? one('SELECT * FROM modules WHERE is_free=0 ORDER BY position, id LIMIT 1') : null;
        flash('Добро пожаловать! Первый модуль уже открыт.');
        redirect($paid ? 'pay.php?m=' . $paid['id'] : 'index.php');
    }
}
layout_head('Регистрация', '', true); ?>
<div class="auth">
  <?php auth_art('5 уроков<br><em>бесплатно</em>', ['Без оплаты и карты', 'Домашки с проверкой', 'Старт за 5 минут']); ?>
  <form class="auth-form" method="post">
    <?= csrf_field() ?>
    <h1>Регистрация</h1>
    <p class="lead">Создайте аккаунт, чтобы открыть бесплатный модуль.</p>
    <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
    <label class="field">Имя и фамилия<small>Так будет написано в сертификате</small><input type="text" name="name" required maxlength="80" autocomplete="name" value="<?= e(post('name')) ?>"></label>
    <label class="field">Почта<input type="email" name="email" required autocomplete="email" value="<?= e(post('email')) ?>"></label>
    <label class="field">Пароль<input type="password" name="password" required minlength="6" autocomplete="new-password"></label>
    <input type="hidden" name="buy" value="<?= !empty($_REQUEST['buy']) ? 1 : '' ?>">
    <?php consent_checks(true); ?>
    <button class="btn btn-blue mt" type="submit">Открыть уроки</button>
    <p class="auth-switch">Уже есть аккаунт? <a href="<?= url('login.php' . (!empty($_REQUEST['buy']) ? '?buy=1' : '')) ?>">Войти</a></p>
  </form>
</div>
<?php layout_foot();
