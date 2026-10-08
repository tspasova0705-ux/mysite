<?php
require __DIR__ . '/../inc/bootstrap.php';
$me = require_admin();

if (is_post()) {
    csrf_check();
    if (post('action') === 'settings') {
        set_setting('pay_url', post('pay_url'));
        set_setting('course_price', post('course_price'));
        set_setting('price_note', post('price_note'));
        set_setting('pay_text', post('pay_text'));
        set_setting('pass_percent', (string)max(1, min(100, (int)post('pass_percent'))));
        set_setting('sequential', isset($_POST['sequential']) ? '1' : '0');
        flash('Настройки сохранены.');
    } elseif (post('action') === 'new_key') {
        set_setting('success_key', bin2hex(random_bytes(12)));
        flash('Создан новый адрес успешной оплаты — обновите его в GetPlatinum.');
    } elseif (post('action') === 'password') {
        if (!password_verify(post('old'), $me['password_hash'])) flash('Текущий пароль неверный.', 'err');
        elseif (mb_strlen(post('new')) < 8) flash('Новый пароль — минимум 8 символов.', 'err');
        else { q('UPDATE users SET password_hash=? WHERE id=?', [password_hash(post('new'), PASSWORD_DEFAULT), $me['id']]); flash('Пароль изменён.'); }
    }
    redirect('admin/settings.php');
}

layout_head('Настройки', 'settings'); ?>
<h1 style="margin:10px 0 18px">Настройки</h1>
<div class="grid2" style="align-items:start">
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="action" value="settings">
    <h2>Курс и оплата</h2>
    <div class="grid2 mt">
      <label class="field">Цена полного курса<input type="text" name="course_price" value="<?= e(setting('course_price', '19 990 ₽')) ?>"></label>
      <label class="field">Подпись к цене<input type="text" name="price_note" value="<?= e(setting('price_note')) ?>"></label>
    </div>
    <label class="field">Общая ссылка на оплату<small>Используется, если у модуля нет своей ссылки</small><input type="url" name="pay_url" value="<?= e(setting('pay_url')) ?>" placeholder="https://"></label>
    <label class="field">Текст на странице оплаты<textarea name="pay_text" style="min-height:80px"><?= e(setting('pay_text')) ?></textarea></label>
    <label class="field">Проходной балл итогового теста, %<input type="number" name="pass_percent" min="1" max="100" value="<?= e(setting('pass_percent', '70')) ?>"></label>
    <label class="check"><input type="checkbox" name="sequential" value="1" <?= setting('sequential', '1') === '1' ? 'checked' : '' ?>> Открывать следующий урок только после того, как домашнее задание предыдущего принято</label>
    <button class="btn btn-blue mt">Сохранить</button>
  </form>
  <div class="stack-sm">
    <form method="post" class="card lime" data-confirm="Старый адрес перестанет открывать курс. Создать новый?">
      <?= csrf_field() ?><input type="hidden" name="action" value="new_key">
      <h2>GetPlatinum</h2>
      <p class="small" style="margin:10px 0 14px">Вставьте эти адреса в настройках оплаты GetPlatinum. После успешной оплаты ученик вернётся в кабинет, и полный курс откроется автоматически.</p>
      <label class="field">Success URL (успешная оплата)<input type="text" readonly value="<?= e(success_url()) ?>" onclick="this.select()"></label>
      <label class="field">Fail URL (оплата не прошла)<input type="text" readonly value="<?= e(str_replace('success.php?k=' . success_key(), 'fail.php', success_url())) ?>" onclick="this.select()"></label>
      <p class="small" style="margin-bottom:12px">Адрес успешной оплаты содержит секретный ключ — не публикуйте его. Если он утёк, создайте новый.</p>
      <button class="btn btn-ink btn-sm">Создать новый адрес</button>
    </form>
    <form method="post" class="card">
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <h2>Пароль администратора</h2>
      <label class="field mt">Текущий пароль<input type="password" name="old" required autocomplete="current-password"></label>
      <label class="field">Новый пароль<input type="password" name="new" required minlength="8" autocomplete="new-password"></label>
      <button class="btn btn-ink">Сменить пароль</button>
    </form>
    <div class="card gray small">
      <b>Ссылки для учеников</b>
      <p class="muted" style="margin-top:6px">Регистрация: <b><?= e(url('register.php')) ?></b><br>Вход: <b><?= e(url('login.php')) ?></b></p>
      <p class="muted" style="margin-top:10px">Лимит загрузки файла на хостинге: <b><?= e(ini_get('upload_max_filesize')) ?></b> (post_max_size <?= e(ini_get('post_max_size')) ?>). Большие видео лучше размещать на Kinescope/Rutube/VK и вставлять ссылкой.</p>
    </div>
  </div>
</div>
<?php layout_foot();
