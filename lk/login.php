<?php
require __DIR__ . '/inc/bootstrap.php';
if (!admin_exists()) redirect('setup.php');
if (current_user()) redirect('index.php');

$error = '';
if (is_post()) {
    csrf_check();
    $u = one('SELECT * FROM users WHERE email=?', [mb_strtolower(post('email'))]);
    if (empty($_POST['agree'])) $error = 'Подтвердите согласие с пользовательским соглашением и офертой.';
    elseif ($u && password_verify(post('password'), $u['password_hash'])) {
        login_user((int)$u['id']);
        if ($u['role'] !== 'admin') apply_pending_payment((int)$u['id']);
        q('UPDATE users SET consent_at=COALESCE(consent_at, ?) WHERE id=?', [now(), $u['id']]);
        $paid = !empty($_POST['buy']) && empty($_SESSION['celebrate']) && $u['role'] !== 'admin' ? one('SELECT * FROM modules WHERE is_free=0 ORDER BY position, id LIMIT 1') : null;
        redirect($u['role'] === 'admin' ? 'admin/index.php' : ($paid ? 'pay.php?m=' . $paid['id'] : 'index.php'));
    } else {
        usleep(400000);
        $error = 'Неверная почта или пароль.';
    }
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
    <input type="hidden" name="buy" value="<?= !empty($_REQUEST['buy']) ? 1 : '' ?>">
    <?php consent_checks(false); ?>
    <button class="btn btn-blue mt" type="submit">Войти</button>
    <p class="auth-switch">Ещё нет доступа? <a href="<?= url('register.php' . (!empty($_REQUEST['buy']) ? '?buy=1' : '')) ?>">Начать бесплатно</a></p>
    <p class="auth-switch" style="margin-top:6px">Забыли пароль? Напишите администратору — он сбросит его.</p>
  </form>
</div>
<?php layout_foot();
