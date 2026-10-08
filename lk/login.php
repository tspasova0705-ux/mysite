<?php
require __DIR__ . '/inc/bootstrap.php';
if (!admin_exists()) redirect('setup.php');
if (current_user()) redirect('index.php');

$error = '';
if (is_post()) {
    csrf_check();
    $u = one('SELECT * FROM users WHERE email=?', [mb_strtolower(post('email'))]);
    if ($u && password_verify(post('password'), $u['password_hash'])) {
        login_user((int)$u['id']);
        redirect($u['role'] === 'admin' ? 'admin/index.php' : 'index.php');
    }
    usleep(400000);
    $error = 'Неверная почта или пароль.';
}
layout_head('Вход', '', true); ?>
<div class="auth">
  <?php auth_art('Личный<br><em>кабинет</em>', ['Уроки и материалы', 'Домашние задания', 'Сертификат']); ?>
  <form class="auth-form" method="post">
    <?= csrf_field() ?>
    <h1>Вход</h1>
    <p class="lead">Рады видеть вас снова — продолжим с того места, где вы остановились.</p>
    <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
    <label class="field">Почта<input type="email" name="email" required autocomplete="email" value="<?= e(post('email')) ?>"></label>
    <label class="field">Пароль<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-blue" type="submit">Войти</button>
    <p class="auth-switch">Ещё нет доступа? <a href="<?= url('register.php') ?>">Начать бесплатно</a></p>
    <p class="auth-switch" style="margin-top:6px">Забыли пароль? Напишите администратору — он сбросит его.</p>
  </form>
</div>
<?php layout_foot();
