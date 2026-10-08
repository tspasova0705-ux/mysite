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
    elseif (empty($_POST['consent'])) $error = 'Нужно согласие на обработку персональных данных.';
    elseif (val('SELECT 1 FROM users WHERE email=?', [$email])) $error = 'Такая почта уже зарегистрирована — войдите.';
    else {
        q("INSERT INTO users(email,name,password_hash,role,created_at) VALUES(?,?,?,'student',?)", [$email, $name, password_hash($pass, PASSWORD_DEFAULT), now()]);
        login_user((int)db()->lastInsertId());
        flash('Добро пожаловать! Первый модуль уже открыт.');
        redirect('index.php');
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
    <label class="check"><input type="checkbox" name="consent" value="1" required> Согласие на обработку персональных данных</label>
    <button class="btn btn-blue mt" type="submit">Открыть уроки</button>
    <p class="auth-switch">Уже есть аккаунт? <a href="<?= url('login.php') ?>">Войти</a></p>
  </form>
</div>
<?php layout_foot();
