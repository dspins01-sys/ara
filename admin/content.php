<?php
require_once __DIR__.'/_header.php';
?>
<div class="builder-head">
  <div><div class="crumb">DESIGN / PAGE BUILDER</div><h1>Visual Website Builder <span class="version-pill">V20.8.3</span></h1><p class="hint">Klik langsung di preview untuk edit: teks, gambar, layout, warna. Seret ⠿ untuk urutkan block. Semua perubahan tersimpan otomatis. Desktop, Tablet, dan Mobile bisa dipreview tanpa mengubah content. Mobile preview memakai viewport 390px, sementara toolbar Builder tetap full-width. Template dan content terpisah — ganti template tidak menghapus content.</p></div>
</div>
<div class="canvas-frame-shell">
  <div class="canvas-frame-bar"><span></span><span></span><span></span><small>Live Canvas</small></div>
  <iframe id="canvasFrame" class="canvas-frame" src="canvas.php" title="Visual Website Builder"></iframe>
</div>
<style>
.crumb{font-size:10px;letter-spacing:.18em;color:#7f8ba4;margin-bottom:7px}
.version-pill{font-size:10px;vertical-align:middle;padding:4px 7px;border:1px solid #394867;border-radius:999px;color:#a7b5d2}
.builder-head{margin-bottom:16px}
.builder-head h1{margin:0 0 5px}
.canvas-frame-shell{background:#070b13;border:1px solid #25304a;border-radius:15px;padding:9px;height:calc(100vh - 190px);min-height:560px;display:flex;flex-direction:column}
.canvas-frame-bar{height:30px;flex:0 0 30px;display:flex;align-items:center;gap:6px;padding:0 8px}
.canvas-frame-bar span{width:8px;height:8px;border-radius:50%;background:#56627a}
.canvas-frame-bar small{margin-left:10px;color:#6a7690;font-size:11px;letter-spacing:.05em}
.canvas-frame{flex:1;width:100%;border:0;border-radius:0 0 10px 10px;background:#fff}
@media(max-width:800px){.canvas-frame-shell{height:calc(100vh - 220px)}}
</style>
<?php require_once __DIR__.'/_footer.php'; ?>
