<?php
require_once __DIR__.'/_header.php';
$pdo=Database::pdo();
$slots=['hero'=>'hero_image','contractor'=>'contractor_image','digital'=>'digital_image','consulting'=>'consulting_image'];
$messages=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $slot=$_POST['slot']??'';
    if(!isset($slots[$slot])) exit('Media slot tidak valid');
    if(isset($_FILES['image'])&&$_FILES['image']['error']===UPLOAD_ERR_OK){
        $f=$_FILES['image'];
        if($f['size']>MAX_UPLOAD_BYTES) exit('File terlalu besar');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime]??null;
        if(!$ext) exit('Format image tidak didukung');
        $name=bin2hex(random_bytes(12)).'.'.$ext;
        if(!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR,0755,true);
        move_uploaded_file($f['tmp_name'],UPLOAD_DIR.$name);
        save_setting($slots[$slot],$name);
        flash(ucfirst($slot).' image berhasil diperbarui.');
    }
    header('Location:media.php');exit;
}
$s=get_settings();
?>
<h1>Media</h1>
<p class="hint">Kelola gambar yang dipakai oleh template website dan Live Preview.</p>
<div class="media-grid">
<?php foreach($slots as $slot=>$key): $file=trim($s[$key]??''); ?>
<div class="media-card"><h2><?=e(ucfirst($slot))?> Image</h2>
<?php if($file): ?><img class="preview" src="../public/uploads/<?=e($file)?>" alt="<?=e($slot)?>">
<?php else: ?><img class="preview" src="../public/assets/images/<?=e($slot==='hero'?'hero.jpg':($slot==='digital'?'team.jpg':'contractor.jpg'))?>" alt="default">
<?php endif; ?>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="slot" value="<?=e($slot)?>"><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required><button>Upload / Replace</button></form>
<?php if($file): ?><small class="file-name"><?=e($file)?></small><?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<p>Upload image maksimal 5MB. Format JPG, PNG, WEBP, GIF.</p>
<style>.media-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.media-card h2{margin-top:0;font-size:18px}.file-name{display:block;color:#697691;margin-top:10px;word-break:break-all}@media(max-width:800px){.media-grid{grid-template-columns:1fr}}</style>
<?php require_once __DIR__.'/_footer.php';?>
