<?php
require __DIR__ . '/inc/bootstrap.php';
$user = require_login();

$f = one('SELECT * FROM files WHERE id=?', [get_int('id')]);
$path = $f ? STORAGE_DIR . '/files/' . $f['stored_name'] : '';
if (!$f || !is_file($path)) { http_response_code(404); exit('Файл не найден'); }

// Access: admin — everything; homework files — only the author; lesson files — whoever can open the lesson.
$ok = $user['role'] === 'admin';
if (!$ok && $f['kind'] === 'hw') $ok = (int)$f['user_id'] === (int)$user['id'];
elseif (!$ok && $f['lesson_id']) {
    $lesson = one('SELECT * FROM lessons WHERE id=?', [$f['lesson_id']]);
    $ok = $lesson && can_view_lesson($user, $lesson);
}
if (!$ok) { http_response_code(403); exit('Нет доступа'); }

session_write_close();
$size = filesize($path);
$start = 0; $end = $size - 1;
// Only media and PDF may open in the browser; everything else (html, txt…) is always a download.
$inline = empty($_GET['dl']) && preg_match('~^(image/(png|jpeg|gif|webp)|video/|audio/|application/pdf$)~', $f['mime']);
header('Content-Type: ' . ($inline ? $f['mime'] : 'application/octet-stream'));
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: sandbox; default-src \'none\'; img-src \'self\'; media-src \'self\'; object-src \'self\'');
header("Content-Disposition: " . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($f['orig_name']));
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=3600');

// Byte ranges so video can be scrubbed.
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] === '' && $m[2] !== '') { $start = max(0, $size - (int)$m[2]); }
    else { $start = (int)$m[1]; if ($m[2] !== '') $end = min((int)$m[2], $size - 1); }
    if ($start > $end || $start >= $size) { http_response_code(416); header("Content-Range: bytes */$size"); exit; }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}
header('Content-Length: ' . ($end - $start + 1));

$fp = fopen($path, 'rb');
fseek($fp, $start);
$left = $end - $start + 1;
while ($left > 0 && !feof($fp) && !connection_aborted()) {
    $chunk = fread($fp, (int)min(1 << 20, $left));
    echo $chunk; flush();
    $left -= strlen($chunk);
}
fclose($fp);
