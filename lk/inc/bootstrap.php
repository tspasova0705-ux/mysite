<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? '1' : '0');
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Moscow');

session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_name('lk_sid');
session_start();

// Base URL of the cabinet (works whether it lives at /lk or elsewhere).
$__dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/lk/index.php')), '/');
if (str_ends_with($__dir, '/admin')) $__dir = substr($__dir, 0, -6);
define('BASE', $__dir);

function url(string $path = ''): string { return BASE . '/' . ltrim($path, '/'); }
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path))); exit; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function post(string $k, string $d = ''): string { return trim((string)($_POST[$k] ?? $d)); }
function get_int(string $k): int { return (int)($_GET[$k] ?? 0); }

/* ---------- database ---------- */

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    foreach ([STORAGE_DIR, STORAGE_DIR . '/data', STORAGE_DIR . '/files'] as $d) {
        if (!is_dir($d)) mkdir($d, 0775, true);
    }
    // No file extension on purpose: front nginx on shared hosting serves files by extension directly.
    $pdo = new PDO('sqlite:' . STORAGE_DIR . '/data/course', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    migrate($pdo);
    return $pdo;
}

function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function one(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function all(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function val(string $sql, array $p = []): mixed { $r = q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }

function migrate(PDO $pdo): void {
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS users(
        id INTEGER PRIMARY KEY, email TEXT UNIQUE NOT NULL, name TEXT NOT NULL,
        password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'student', created_at TEXT NOT NULL,
        consent_at TEXT);
    CREATE TABLE IF NOT EXISTS modules(
        id INTEGER PRIMARY KEY, position INTEGER NOT NULL, title TEXT NOT NULL, subtitle TEXT NOT NULL DEFAULT '',
        is_free INTEGER NOT NULL DEFAULT 0, price_label TEXT NOT NULL DEFAULT '', pay_url TEXT NOT NULL DEFAULT '');
    CREATE TABLE IF NOT EXISTS lessons(
        id INTEGER PRIMARY KEY, module_id INTEGER NOT NULL REFERENCES modules(id) ON DELETE CASCADE,
        position INTEGER NOT NULL, title TEXT NOT NULL, body TEXT NOT NULL DEFAULT '',
        video_url TEXT NOT NULL DEFAULT '', video_file_id INTEGER, homework_intro TEXT NOT NULL DEFAULT '');
    CREATE TABLE IF NOT EXISTS hw_items(
        id INTEGER PRIMARY KEY, lesson_id INTEGER NOT NULL REFERENCES lessons(id) ON DELETE CASCADE,
        position INTEGER NOT NULL, text TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS files(
        id INTEGER PRIMARY KEY, lesson_id INTEGER, user_id INTEGER, kind TEXT NOT NULL,
        stored_name TEXT NOT NULL, orig_name TEXT NOT NULL, mime TEXT NOT NULL, size INTEGER NOT NULL,
        comment TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS submissions(
        id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        lesson_id INTEGER NOT NULL REFERENCES lessons(id) ON DELETE CASCADE,
        status TEXT NOT NULL DEFAULT 'submitted', admin_comment TEXT NOT NULL DEFAULT '',
        updated_at TEXT NOT NULL, UNIQUE(user_id, lesson_id));
    CREATE TABLE IF NOT EXISTS answers(
        id INTEGER PRIMARY KEY, submission_id INTEGER NOT NULL REFERENCES submissions(id) ON DELETE CASCADE,
        item_id INTEGER NOT NULL REFERENCES hw_items(id) ON DELETE CASCADE,
        answer_text TEXT NOT NULL DEFAULT '', file_id INTEGER, mark TEXT NOT NULL DEFAULT 'pending',
        admin_note TEXT NOT NULL DEFAULT '', UNIQUE(submission_id, item_id));
    CREATE TABLE IF NOT EXISTS access(
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        module_id INTEGER NOT NULL REFERENCES modules(id) ON DELETE CASCADE,
        granted_at TEXT NOT NULL, PRIMARY KEY(user_id, module_id));
    CREATE TABLE IF NOT EXISTS payment_requests(
        id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        module_id INTEGER NOT NULL REFERENCES modules(id) ON DELETE CASCADE,
        note TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'new', created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS test_questions(
        id INTEGER PRIMARY KEY, position INTEGER NOT NULL, text TEXT NOT NULL,
        type TEXT NOT NULL DEFAULT 'single', options TEXT NOT NULL DEFAULT '[]', correct INTEGER NOT NULL DEFAULT 0);
    CREATE TABLE IF NOT EXISTS test_attempts(
        id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        score INTEGER NOT NULL, total INTEGER NOT NULL, passed INTEGER NOT NULL, answers TEXT NOT NULL, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS certificates(
        id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
        code TEXT NOT NULL UNIQUE, score INTEGER NOT NULL, issued_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS settings(key TEXT PRIMARY KEY, value TEXT NOT NULL);
    SQL);

    $userCols = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(), 'name');
    if (!in_array('consent_at', $userCols, true)) $pdo->exec('ALTER TABLE users ADD COLUMN consent_at TEXT');

    if ((int)$pdo->query('SELECT COUNT(*) FROM modules')->fetchColumn() === 0) seed_course($pdo);
}

function seed_course(PDO $pdo): void {
    $data = json_decode((string)file_get_contents(__DIR__ . '/seed.json'), true) ?: [];
    $pdo->beginTransaction();
    foreach ($data as $mi => $m) {
        $pdo->prepare('INSERT INTO modules(position,title,subtitle,is_free,price_label) VALUES(?,?,?,?,?)')
            ->execute([$mi + 1, $m['title'], $m['subtitle'], $m['free'] ? 1 : 0, '']);
        $mid = (int)$pdo->lastInsertId();
        foreach ($m['lessons'] as $li => $l) {
            $pdo->prepare('INSERT INTO lessons(module_id,position,title,body,homework_intro) VALUES(?,?,?,?,?)')
                ->execute([$mid, $li + 1, $l['title'], $l['body'], 'Выполните каждый пункт и отправьте на проверку. Администратор отметит каждую графу.']);
            $lid = (int)$pdo->lastInsertId();
            foreach ($l['hw'] as $hi => $h) {
                $pdo->prepare('INSERT INTO hw_items(lesson_id,position,text) VALUES(?,?,?)')->execute([$lid, $hi + 1, $h]);
            }
        }
    }
    $questions = json_decode((string)file_get_contents(__DIR__ . '/seed_test.json'), true) ?: [];
    foreach ($questions as $i => $t) {
        $pdo->prepare('INSERT INTO test_questions(position,text,type,options,correct) VALUES(?,?,?,?,?)')
            ->execute([$i + 1, $t['text'], $t['type'], json_encode($t['options'] ?? [], JSON_UNESCAPED_UNICODE), $t['correct'] ?? 0]);
    }
    seed_bonus_files($pdo);
    $defaults = ['pass_percent' => '70', 'sequential' => '1', 'pay_url' => '',
        'course_price' => '19 990 ₽', 'price_note' => 'Сегодня 0 ₽ — доступна рассрочка', 'pay_text' => 'После оплаты нажмите «Я оплатил(а)» — администратор откроет доступ.'];
    foreach ($defaults as $k => $v) $pdo->prepare('INSERT OR IGNORE INTO settings(key,value) VALUES(?,?)')->execute([$k, $v]);
    $pdo->commit();
}

/** Attach the bonus templates (lk/inc/bonus) to the lessons they belong to. */
function seed_bonus_files(PDO $pdo): void {
    $bonus = [
        'Как читать оффер' => ['pasport-offera.xlsx', 'Паспорт оффера.xlsx', 'Бонус: шаблон паспорта оффера. Заполните его для выбранного оффера — это и есть часть домашнего задания.'],
        'Аналитика до запуска' => ['tablica-analitiki.xlsx', 'Таблица аналитики.xlsx', 'Бонус: таблица аналитики. Вносите расход, клики, лиды и выплаты — CTR, CPL, CPA и прибыль считаются автоматически.'],
        'Контент на месяц' => ['kontent-plan-30-dney.xlsx', 'Контент-план на 30 дней.xlsx', 'Бонус: контент-план на 30 дней. Подставьте свой сегмент и оффер, отмечайте готовые публикации.'],
        'Первый 30-дневный тест' => ['chek-list-pervogo-testa.xlsx', 'Чек-лист первого теста.xlsx', 'Бонус: чек-лист первого теста — пройдите все шаги по порядку.'],
    ];
    foreach ($bonus as $lessonTitle => [$src, $name, $comment]) {
        $lid = $pdo->prepare('SELECT id FROM lessons WHERE title=? LIMIT 1');
        $lid->execute([$lessonTitle]);
        $lid = $lid->fetchColumn();
        $path = __DIR__ . '/bonus/' . $src;
        if (!$lid || !is_file($path)) continue;
        $stored = bin2hex(random_bytes(16));
        if (!copy($path, STORAGE_DIR . '/files/' . $stored)) continue;
        $pdo->prepare("INSERT INTO files(lesson_id,user_id,kind,stored_name,orig_name,mime,size,comment,created_at) VALUES(?,NULL,'material',?,?,?,?,?,?)")
            ->execute([$lid, $stored, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', filesize($path), $comment, now()]);
    }
}

function setting(string $k, string $d = ''): string { return (string)(val('SELECT value FROM settings WHERE key=?', [$k]) ?? $d); }
function set_setting(string $k, string $v): void { q('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value', [$k, $v]); }
function now(): string { return date('Y-m-d H:i:s'); }

/** A module without its own price is sold as part of the full course. */
function sold_with_course(array $m): bool { return (int)$m['is_free'] === 0 && trim($m['price_label']) === ''; }
function price_of(array $m): string { return sold_with_course($m) ? setting('course_price', '19 990 ₽') : $m['price_label']; }

/** Grant access after payment: a full-course purchase opens every module sold with the course. */
function grant_paid_access(int $userId, int $moduleId): void {
    $m = one('SELECT * FROM modules WHERE id=?', [$moduleId]);
    $ids = $m && sold_with_course($m)
        ? array_column(array_filter(modules(), 'sold_with_course'), 'id')
        : [$moduleId];
    foreach ($ids as $id) q('INSERT OR IGNORE INTO access(user_id,module_id,granted_at) VALUES(?,?,?)', [$userId, $id, now()]);
    q("UPDATE payment_requests SET status='approved' WHERE user_id=? AND status='new' AND module_id IN (" . implode(',', array_map('intval', $ids)) . ')', [$userId]);
}

/* ---------- csrf & flash ---------- */

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Сессия устарела. Обновите страницу и попробуйте снова.');
    }
}
function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'][] = [$type, $msg]; }
function take_flash(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

/* ---------- auth ---------- */

function current_user(): ?array {
    static $u = false;
    if ($u === false) $u = empty($_SESSION['uid']) ? null : one('SELECT * FROM users WHERE id=?', [$_SESSION['uid']]);
    return $u;
}
function is_admin(): bool { return (current_user()['role'] ?? '') === 'admin'; }
function require_login(): array {
    if (!admin_exists()) redirect('setup.php');
    $u = current_user();
    if (!$u) redirect('login.php');
    return $u;
}
function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') redirect('index.php');
    return $u;
}
function login_user(int $id): void { session_regenerate_id(true); $_SESSION['uid'] = $id; }
function admin_exists(): bool { return (int)val("SELECT COUNT(*) FROM users WHERE role='admin'") > 0; }

/* ---------- course logic ---------- */

function modules(): array { return all('SELECT * FROM modules ORDER BY position, id'); }
function module_lessons(int $mid): array { return all('SELECT * FROM lessons WHERE module_id=? ORDER BY position, id', [$mid]); }

/** All lessons in course order, each with module fields. */
function course_lessons(): array {
    static $cache = null;
    return $cache ??= all('SELECT l.*, m.position AS m_pos, m.is_free FROM lessons l JOIN modules m ON m.id=l.module_id ORDER BY m.position, m.id, l.position, l.id');
}

function has_module_access(array $user, array $module): bool {
    if ($user['role'] === 'admin' || (int)$module['is_free'] === 1) return true;
    return (bool)val('SELECT 1 FROM access WHERE user_id=? AND module_id=?', [$user['id'], $module['id']]);
}

function hw_count(int $lessonId): int {
    static $c = null;
    if ($c === null) { $c = []; foreach (all('SELECT lesson_id, COUNT(*) n FROM hw_items GROUP BY lesson_id') as $r) $c[(int)$r['lesson_id']] = (int)$r['n']; }
    return $c[$lessonId] ?? 0;
}

function submission_for(int $userId, int $lessonId): ?array {
    return one('SELECT * FROM submissions WHERE user_id=? AND lesson_id=?', [$userId, $lessonId]);
}

/** Lesson counts as passed when it has no homework or the homework is accepted. */
function lesson_done(int $userId, int $lessonId): bool {
    if (hw_count($lessonId) === 0) return true;
    return val("SELECT status FROM submissions WHERE user_id=? AND lesson_id=?", [$userId, $lessonId]) === 'accepted';
}

/**
 * State of a lesson for a user:
 * pay (module not paid), wait (previous homework not accepted yet),
 * open, submitted, returned, accepted.
 */
function lesson_state(array $user, array $lesson): string {
    $module = one('SELECT * FROM modules WHERE id=?', [$lesson['module_id']]);
    if (!$module || !has_module_access($user, $module)) return 'pay';
    $sub = submission_for((int)$user['id'], (int)$lesson['id']);
    if ($sub) return $sub['status'];
    if ($user['role'] !== 'admin' && setting('sequential', '1') === '1') {
        $prev = null;
        foreach (course_lessons() as $l) {
            if ((int)$l['id'] === (int)$lesson['id']) break;
            $prev = $l;
        }
        if ($prev && !lesson_done((int)$user['id'], (int)$prev['id'])) return 'wait';
    }
    return 'open';
}

function can_view_lesson(array $user, array $lesson): bool { return !in_array(lesson_state($user, $lesson), ['pay', 'wait'], true); }

/** Recompute submission status from per-item marks. */
function refresh_submission(int $subId): string {
    $marks = array_column(all('SELECT mark FROM answers WHERE submission_id=?', [$subId]), 'mark');
    // Any item sent back -> returned to the student; all items done -> accepted.
    if (in_array('redo', $marks, true)) $status = 'returned';
    elseif ($marks && !in_array('pending', $marks, true)) $status = 'accepted';
    else $status = 'submitted';
    q('UPDATE submissions SET status=?, updated_at=? WHERE id=?', [$status, now(), $subId]);
    return $status;
}

function progress(array $user): array {
    // Progress = accepted homeworks out of lessons that have homework.
    $total = 0; $done = 0;
    foreach (course_lessons() as $l) {
        if (hw_count((int)$l['id']) === 0) continue;
        $total++;
        if (lesson_done((int)$user['id'], (int)$l['id'])) $done++;
    }
    return [$done, $total, $total ? (int)round($done * 100 / $total) : 0];
}

function course_completed(array $user): bool {
    foreach (course_lessons() as $l) {
        if (hw_count((int)$l['id']) > 0 && !lesson_done((int)$user['id'], (int)$l['id'])) return false;
    }
    return true;
}

const STATE_LABELS = [
    'pay' => ['Платный модуль', 'lock'],
    'wait' => ['Откроется после проверки', 'lock'],
    'open' => ['Доступен', 'open'],
    'submitted' => ['На проверке', 'wait'],
    'returned' => ['Нужна доработка', 'redo'],
    'accepted' => ['Выполнено', 'done'],
];
function state_chip(string $state): string {
    [$label, $cls] = STATE_LABELS[$state] ?? [$state, 'open'];
    return '<span class="chip chip-' . $cls . '">' . e($label) . '</span>';
}

/* ---------- files ---------- */

const ALLOWED_EXT = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','rtf','odt','ods','zip','rar','7z',
    'jpg','jpeg','png','gif','webp','heic','mp4','mov','webm','m4v','mp3','m4a','wav'];
const VIDEO_EXT = ['mp4','mov','webm','m4v'];

/** Save an uploaded file; returns file id or null when nothing was uploaded. Throws on invalid file. */
function store_upload(array $f, string $kind, ?int $lessonId, ?int $userId, string $comment = '', ?array $onlyExt = null): ?int {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'Файл больше, чем разрешает хостинг (' . ini_get('upload_max_filesize') . ').' : 'Файл не загрузился, попробуйте ещё раз.');
    }
    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    $allowed = $onlyExt ?? ALLOWED_EXT;
    if (!in_array($ext, $allowed, true)) throw new RuntimeException('Такой тип файла нельзя загрузить. Разрешены: ' . implode(', ', $allowed) . '.');
    if ($f['size'] > MAX_UPLOAD_MB * 1024 * 1024) throw new RuntimeException('Файл больше ' . MAX_UPLOAD_MB . ' МБ.');
    $stored = bin2hex(random_bytes(16)); // no extension: never executable, never served directly
    if (!move_uploaded_file($f['tmp_name'], STORAGE_DIR . '/files/' . $stored)) throw new RuntimeException('Не удалось сохранить файл.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file(STORAGE_DIR . '/files/' . $stored) ?: 'application/octet-stream';
    q('INSERT INTO files(lesson_id,user_id,kind,stored_name,orig_name,mime,size,comment,created_at) VALUES(?,?,?,?,?,?,?,?,?)',
        [$lessonId, $userId, $kind, $stored, mb_substr(basename((string)$f['name']), 0, 180), $mime, (int)$f['size'], $comment, now()]);
    return (int)db()->lastInsertId();
}

function delete_file(int $id): void {
    $f = one('SELECT * FROM files WHERE id=?', [$id]);
    if (!$f) return;
    @unlink(STORAGE_DIR . '/files/' . $f['stored_name']);
    q('DELETE FROM files WHERE id=?', [$id]);
}

function human_size(int $b): string {
    foreach (['Б', 'КБ', 'МБ', 'ГБ'] as $u) { if ($b < 1024) return round($b, 1) . ' ' . $u; $b /= 1024; }
    return round($b, 1) . ' ТБ';
}

/** Turn a YouTube / Rutube / VK / Kinescope link into an embeddable player URL. */
function video_embed(string $u): ?string {
    $u = trim($u);
    if ($u === '') return null;
    if (preg_match('~src="([^"]+)"~', $u, $m)) $u = html_entity_decode($m[1]); // pasted <iframe> code
    if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/|live/)|youtu\.be/)([\w-]{6,})~', $u, $m)) return 'https://www.youtube.com/embed/' . $m[1];
    if (preg_match('~rutube\.ru/(?:video|play/embed)/(?:private/)?([0-9a-f]{20,})~', $u, $m)) return 'https://rutube.ru/play/embed/' . $m[1] . (preg_match('~p=([\w-]+)~', $u, $p) ? '?p=' . $p[1] : '');
    if (preg_match('~vk\.(?:com|ru)/video_ext\.php~', $u)) return $u;
    if (preg_match('~vk(?:video)?\.(?:com|ru)/.*video(-?\d+)_(\d+)~', $u, $m)) return 'https://vk.com/video_ext.php?oid=' . $m[1] . '&id=' . $m[2] . '&hd=2';
    if (preg_match('~kinescope\.io/(?:embed/)?([\w-]+)~', $u, $m)) return 'https://kinescope.io/embed/' . $m[1];
    if (str_starts_with($u, 'https://')) return $u;
    return null;
}

/** Lesson body: "## Heading" lines become headings, the rest paragraphs. */
function render_body(string $body): string {
    $html = '';
    foreach (preg_split("~\n{2,}~", trim($body)) as $block) {
        $lines = explode("\n", trim($block));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (str_starts_with($line, '## ')) $html .= '<h3>' . e(substr($line, 3)) . '</h3>';
            elseif (str_starts_with($line, '«')) $html .= '<p class="voice">' . e($line) . '</p>';
            else $html .= '<p>' . e($line) . '</p>';
        }
    }
    return $html;
}

function plural(int $n, string $one, string $few, string $many): string {
    $n10 = $n % 10; $n100 = $n % 100;
    if ($n10 === 1 && $n100 !== 11) return $one;
    if ($n10 >= 2 && $n10 <= 4 && ($n100 < 10 || $n100 >= 20)) return $few;
    return $many;
}
require_once __DIR__ . '/layout.php';
