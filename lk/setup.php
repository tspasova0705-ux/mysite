<?php
require __DIR__ . '/inc/bootstrap.php';
// One-time page: creates the first administrator, then locks itself.
if (admin_exists()) redirect('login.php');

$error = '';
if (is_post()) {
    csrf_check();
    $name = post('name'); $email = mb_strtolower(post('email')); $pass = post('password');
    if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Проверьте имя и почту.';
    elseif (mb_strlen($pass) < 8) $error = 'Пароль администратора — минимум 8 символов.';
    else {
        $existing = val('SELECT id FROM users WHERE email=?', [$email]);
        if ($existing) q("UPDATE users SET role='admin', name=?, password_hash=? WHERE id=?", [$name, password_hash($pass, PASSWORD_DEFAULT), $existing]);
        else q("INSERT INTO users(email,name,password_hash,role,created_at) VALUES(?,?,?,'admin',?)", [$email, $name, password_hash($pass, PASSWORD_DEFAULT), now()]);
        login_user((int)($existing ?: db()->lastInsertId()));
        flash('Администратор создан. Курс уже заполнен уроками из вашего документа.');
        redirect('admin/index.php');
    }
}
layout_head('Первый запуск', '', true); ?>
<div class="auth">
  <?php auth_art('Первый<br><em>запуск</em>', ['Создайте администратора', 'Это делается один раз']); ?>
  <form class="auth-form" method="post">
    <?= csrf_field() ?>
    <h1>Администратор</h1>
    <p class="lead">Эта страница открывается только один раз — пока администратора нет.</p>
    <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
    <label class="field">Имя<input type="text" name="name" required value="<?= e(post('name')) ?>"></label>
    <label class="field">Почта<input type="email" name="email" required value="<?= e(post('email')) ?>"></label>
    <label class="field">Пароль<small>Минимум 8 символов</small><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
    <button class="btn btn-blue" type="submit">Создать и войти</button>
  </form>
</div>
<?php layout_foot();
