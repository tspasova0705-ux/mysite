<?php
require __DIR__ . '/inc/bootstrap.php';
$user = require_login();

$f = one("SELECT * FROM files WHERE id=? AND kind='material'", [get_int('id')]);
if (!$f || !can_access_file($user, $f)) { flash('Файл недоступен.', 'err'); redirect('index.php'); }
if (!is_view_only($f)) redirect('file.php?id=' . $f['id'] . '&dl=1');
$lesson = $f['lesson_id'] ? one('SELECT id, title FROM lessons WHERE id=?', [$f['lesson_id']]) : null;
// Watermark: who opened the file. Discourages passing screenshots around.
$mark = $user['name'] . ' · ' . $user['email'];

layout_head(preg_replace('~\.pdf$~i', '', $f['orig_name']), 'home'); ?>
<style>
  .viewer-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin:6px 0 16px}
  .viewer-head h1{font-size:clamp(20px,3vw,28px)}
  .pages{display:flex;flex-direction:column;gap:14px;max-width:1000px;margin:0 auto;user-select:none;-webkit-user-select:none;-webkit-touch-callout:none}
  .pages canvas{width:100%;height:auto;display:block;border-radius:14px;background:#fff;box-shadow:0 2px 14px rgba(18,18,18,.08)}
  .viewer-status{text-align:center;padding:40px 0;color:var(--muted,#6b6b6b)}
  @media print{body{display:none!important}}
</style>
<div class="crumbs"><a href="<?= url('index.php') ?>">Мои уроки</a><?php if ($lesson): ?><span>/</span><a href="<?= url('lesson.php?id=' . $lesson['id']) ?>"><?= e($lesson['title']) ?></a><?php endif; ?></div>
<div class="viewer-head">
  <h1><?= e(preg_replace('~\.pdf$~i', '', $f['orig_name'])) ?></h1>
  <?php if ($lesson): ?><a class="btn btn-line btn-sm" href="<?= url('lesson.php?id=' . $lesson['id']) ?>#homework">← К уроку и домашке</a><?php endif; ?>
</div>
<?php if ($f['comment']): ?><p class="muted" style="margin:-6px 0 16px"><?= nl2br(e($f['comment'])) ?></p><?php endif; ?>
<div class="pages" id="pages"><div class="viewer-status" id="status">Загружаем гайд…</div></div>

<script src="<?= url('assets/pdfjs/pdf.min.js') ?>"></script>
<script>
(function () {
  var src = <?= json_encode(url('file.php?id=' . $f['id'])) ?>, mark = <?= json_encode($mark, JSON_UNESCAPED_UNICODE) ?>;
  var box = document.getElementById('pages'), status = document.getElementById('status');
  pdfjsLib.GlobalWorkerOptions.workerSrc = <?= json_encode(url('assets/pdfjs/pdf.worker.min.js')) ?>;
  box.addEventListener('contextmenu', function (e) { e.preventDefault(); });
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && /^[sp]$/i.test(e.key)) e.preventDefault(); // no save / print shortcuts
  });

  function watermark(ctx, w, h) {
    ctx.save();
    ctx.globalAlpha = 0.09; ctx.fillStyle = '#121212';
    ctx.font = Math.round(w / 48) + 'px Arial, sans-serif';
    ctx.translate(w / 2, h / 2); ctx.rotate(-Math.PI / 9);
    var step = w / 3.2;
    for (var y = -h; y < h; y += step / 2.2) for (var x = -w; x < w; x += step) ctx.fillText(mark, x, y);
    ctx.restore();
  }

  fetch(src, { credentials: 'same-origin', headers: { 'X-Guide-Viewer': '1' } })
    .then(function (r) { if (!r.ok) throw new Error(r.status); return r.arrayBuffer(); })
    .then(function (buf) { return pdfjsLib.getDocument({ data: buf }).promise; })
    .then(function (pdf) {
      status.remove();
      var width = Math.min(box.clientWidth, 1000), ratio = Math.min(window.devicePixelRatio || 1, 2);
      var chain = Promise.resolve();
      for (var i = 1; i <= pdf.numPages; i++) (function (n) {
        chain = chain.then(function () { return pdf.getPage(n); }).then(function (page) {
          var vp = page.getViewport({ scale: 1 }), scale = width * ratio / vp.width;
          var v = page.getViewport({ scale: scale }), c = document.createElement('canvas');
          c.width = Math.floor(v.width); c.height = Math.floor(v.height);
          box.appendChild(c);
          var ctx = c.getContext('2d');
          return page.render({ canvasContext: ctx, viewport: v }).promise.then(function () { watermark(ctx, c.width, c.height); });
        });
      })(i);
      return chain;
    })
    .catch(function () { status.textContent = 'Не удалось открыть гайд. Обновите страницу или напишите нам в «Сообщения».'; });
})();
</script>
<?php layout_foot();
