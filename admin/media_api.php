<?php
require_once __DIR__.'/../app/Security.php'; require_once __DIR__.'/../app/Content.php';
admin_required(); header('Content-Type: application/json; charset=utf-8');
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf(); if(!isset($_FILES['image'])||$_FILES['image']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('File upload tidak valid');
  $f=$_FILES['image']; if($f['size']>MAX_UPLOAD_BYTES) throw new RuntimeException('File terlalu besar');
  $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']); $map=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif']; if(!isset($map[$mime])) throw new RuntimeException('Format image tidak didukung');
  if(!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR,0755,true); $name=bin2hex(random_bytes(12)).'.'.$map[$mime]; if(!move_uploaded_file($f['tmp_name'],UPLOAD_DIR.$name)) throw new RuntimeException('Gagal menyimpan file');
  echo json_encode(['ok'=>true,'file'=>$name,'url'=>'../public/uploads/'.rawurlencode($name)]); exit;
 }
 $files=[]; if(is_dir(UPLOAD_DIR)){foreach(glob(UPLOAD_DIR.'*') as $p){if(is_file($p)){ $n=basename($p); if(preg_match('/\.(jpg|jpeg|png|webp|gif)$/i',$n))$files[]=['file'=>$n,'url'=>'../public/uploads/'.rawurlencode($n)];}}}
 echo json_encode($files); exit;
}catch(Throwable $e){http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
