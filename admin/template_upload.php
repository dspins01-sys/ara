<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Security.php';
require_once __DIR__.'/../app/Content.php';

/**
 * Template ZIP upload endpoint.
 * Always returns JSON, even when PHP emits a warning/exception.
 */
ob_start();
header('Content-Type: application/json; charset=utf-8');

function template_json_response(array $payload, int $status=200): void {
  while (ob_get_level() > 0) { ob_end_clean(); }
  http_response_code($status);
  echo json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
  exit;
}

set_error_handler(function(int $severity, string $message, string $file, int $line): bool {
  if (!(error_reporting() & $severity)) return false;
  throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
  admin_required();
  verify_csrf();

  function template_safe_zip_name(string $name): bool {
    return $name!==''
      && strpos($name,'..')===false
      && !preg_match('~^[/\\\\]~',$name)
      && !preg_match('~^[A-Za-z]:~',$name);
  }

  function template_clean_block(array $b, string $slug): array {
    $allowed=['title','subtitle','body','image','image2','image3','layout','block_type','button_text','button_url','bg_color','text_color','padding_top','padding_bottom','max_width','custom_class','is_active'];
    $out=[];
    foreach($allowed as $k) if(array_key_exists($k,$b)) $out[$k]=$b[$k];
    foreach(['title','subtitle','button_text','button_url','bg_color','text_color','custom_class','image','image2','image3'] as $k) $out[$k]=trim((string)($out[$k]??''));
    $out['body']=sanitize_rich_html((string)($out['body']??'<p>Write something great here…</p>'));
    $out['layout']=in_array(($out['layout']??'image-right'),['image-right','image-left','center','full'],true)?$out['layout']:'image-right';
    $out['block_type']=in_array(($out['block_type']??'feature'),['hero','feature','text','image-text','gallery','quote','cta','spacer','about','contact'],true)?$out['block_type']:'feature';
    $out['padding_top']=max(0,min(600,(int)($out['padding_top']??108)));
    $out['padding_bottom']=max(0,min(600,(int)($out['padding_bottom']??108)));
    $out['max_width']=min(1800,max(320,(int)($out['max_width']??900)));
    $out['is_active']=array_key_exists('is_active',$out)?(int)((bool)$out['is_active']):1;
    foreach(['image','image2','image3'] as $k){
      if(strpos($out[$k],'assets/')===0) $out[$k]='templates/'.$slug.'/'.ltrim(substr($out[$k],7),'/');
      elseif($out[$k]!=='' && strpos($out[$k],'templates/')!==0 && strpos($out[$k],'uploads/')!==0) $out[$k]='';
    }
    return $out;
  }

  if(!class_exists('ZipArchive')) template_json_response(['ok'=>false,'error'=>'PHP ZipArchive belum aktif di server. Aktifkan extension zip lalu coba lagi.'],500);
  if(empty($_FILES['template'])) template_json_response(['ok'=>false,'error'=>'File template tidak diterima server.'],400);
  if($_FILES['template']['error']!==UPLOAD_ERR_OK) {
    $codes=[UPLOAD_ERR_INI_SIZE=>'File melebihi upload_max_filesize PHP.',UPLOAD_ERR_FORM_SIZE=>'File melebihi batas form.',UPLOAD_ERR_PARTIAL=>'Upload file tidak lengkap.',UPLOAD_ERR_NO_FILE=>'Tidak ada file yang dikirim.'];
    template_json_response(['ok'=>false,'error'=>$codes[$_FILES['template']['error']]??'Upload file gagal.'],400);
  }
  if((int)$_FILES['template']['size']>5*1024*1024) template_json_response(['ok'=>false,'error'=>'Ukuran template maksimal 5 MB.'],400);

  $zip=new ZipArchive();
  $opened=$zip->open($_FILES['template']['tmp_name']);
  if($opened!==true) template_json_response(['ok'=>false,'error'=>'ZIP template tidak dapat dibuka. Kode ZIP: '.$opened],400);

  $manifestRaw=$zip->getFromName('manifest.json');
  $cssRaw=$zip->getFromName('style.css');
  if($manifestRaw===false || $cssRaw===false){ $zip->close(); template_json_response(['ok'=>false,'error'=>'Template harus berisi manifest.json dan style.css.'],400); }

  $manifest=json_decode($manifestRaw,true);
  if(!is_array($manifest)){
    $err=json_last_error_msg();
    $zip->close();
    template_json_response(['ok'=>false,'error'=>'manifest.json tidak valid: '.$err],400);
  }

  $name=trim((string)($manifest['name']??''));
  if($name===''){ $zip->close(); template_json_response(['ok'=>false,'error'=>'manifest.json membutuhkan field "name".'],400); }
  $slug=preg_replace('/[^a-z0-9-]+/i','-',strtolower($name));
  $slug=trim($slug,'-');
  if($slug==='') $slug='template-'.bin2hex(random_bytes(3));
  $slug=substr($slug,0,40);

  if(strlen($cssRaw)>350000 || preg_match('/@import\s|javascript\s*:/i',$cssRaw)){
    $zip->close(); template_json_response(['ok'=>false,'error'=>'style.css terlalu besar atau mengandung import/JavaScript yang tidak didukung.'],400);
  }

  $blocksRaw=$manifest['blocks']??$manifest['content']??[];
  if(!is_array($blocksRaw)){ $zip->close(); template_json_response(['ok'=>false,'error'=>'blocks di manifest.json harus berupa array.'],400); }
  if(count($blocksRaw)>30){ $zip->close(); template_json_response(['ok'=>false,'error'=>'Maksimal 30 block per template.'],400); }
  $blocks=[];
  foreach($blocksRaw as $b) if(is_array($b)) $blocks[]=template_clean_block($b,$slug);

  $settings=[];
  if(isset($manifest['settings']) && is_array($manifest['settings'])) foreach(template_allowed_settings() as $k) if(array_key_exists($k,$manifest['settings'])) $settings[$k]=(string)$manifest['settings'][$k];
  foreach(['hero_image','contractor_image','digital_image','consulting_image'] as $ik){
    if(isset($settings[$ik])){
      if(strpos($settings[$ik],'assets/')===0) $settings[$ik]='templates/'.$slug.'/'.ltrim(substr($settings[$ik],7),'/');
      elseif($settings[$ik]!=='' && strpos($settings[$ik],'templates/')!==0 && strpos($settings[$ik],'uploads/')!==0) unset($settings[$ik]);
    }
  }
  if(isset($settings['hero_layout']) && !in_array($settings['hero_layout'],['stacked','split','overlay','grid','slider'],true)) unset($settings['hero_layout']);
  if(isset($settings['site_theme']) && !in_array($settings['site_theme'],['default','minimal','bold'],true)) unset($settings['site_theme']);
  if(isset($settings['accent_color']) && !preg_match('/^#[0-9a-fA-F]{3,8}$/',$settings['accent_color'])) unset($settings['accent_color']);

  $cssDir=__DIR__.'/../public/assets/css/templates';
  $imgRoot=__DIR__.'/../public/assets/images/templates';
  $imgDir=$imgRoot.'/'.$slug;
  if(!is_dir($cssDir) && !mkdir($cssDir,0755,true) && !is_dir($cssDir)) throw new RuntimeException('Folder CSS template tidak bisa dibuat: '.$cssDir);
  if(!is_dir($imgRoot) && !mkdir($imgRoot,0755,true) && !is_dir($imgRoot)) throw new RuntimeException('Folder image template tidak bisa dibuat: '.$imgRoot);
  if(!is_dir($imgDir) && !mkdir($imgDir,0755,true) && !is_dir($imgDir)) throw new RuntimeException('Folder asset template tidak bisa dibuat: '.$imgDir);

  $cssFile=$slug.'.css';
  if(file_put_contents($cssDir.'/'.$cssFile,$cssRaw,LOCK_EX)===false) throw new RuntimeException('style.css tidak bisa disimpan. Cek permission folder public/assets/css/templates.');

  $previewFile='';
  foreach(['preview.jpg','preview.jpeg','preview.png','thumbnail.jpg','thumbnail.png'] as $candidate){
    $img=$zip->getFromName($candidate);
    if($img!==false){
      $ext=strtolower(pathinfo($candidate,PATHINFO_EXTENSION));
      $previewFile=$slug.'.'.$ext;
      if(file_put_contents($imgRoot.'/'.$previewFile,$img,LOCK_EX)===false) throw new RuntimeException('Preview template tidak bisa disimpan. Cek permission folder public/assets/images/templates.');
      break;
    }
  }
  if($previewFile===''){
    $accent=(string)($settings['accent_color']??'#2563eb');
    if(!preg_match('/^#[0-9a-fA-F]{3,8}$/',$accent)) $accent='#2563eb';
    $title=htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $count=count($blocks);
    $svg='';
    $svg.='<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="760" viewBox="0 0 1200 760">';
    $svg.='<rect width="1200" height="760" fill="#f7f8fc"/><rect width="1200" height="6" fill="'.$accent.'"/>';
    $svg.='<rect y="6" width="1200" height="70" fill="#fff"/><text x="48" y="50" font-family="Arial,sans-serif" font-size="22" font-weight="700" fill="#101828">'.$title.'</text>';
    $svg.='<rect y="76" width="1200" height="315" fill="#eef4ff"/><rect x="48" y="116" width="600" height="220" rx="18" fill="#fff"/><text x="82" y="160" font-family="Arial,sans-serif" font-size="12" font-weight="700" fill="'.$accent.'">TEMPLATE PREVIEW</text>';
    $svg.='<text x="82" y="215" font-family="Arial,sans-serif" font-size="42" font-weight="700" fill="#101828">Modern Website</text><text x="82" y="248" font-family="Arial,sans-serif" font-size="42" font-weight="700" fill="#101828">Ready to Edit</text>';
    $svg.='<rect x="82" y="278" width="140" height="34" rx="9" fill="'.$accent.'"/><text x="106" y="301" font-family="Arial,sans-serif" font-size="12" fill="#fff">Get Started</text>';
    $svg.='<rect x="690" y="116" width="462" height="220" rx="18" fill="#c9d9f8"/><rect x="730" y="150" width="260" height="150" rx="10" fill="'.$accent.'"/><rect x="1010" y="150" width="100" height="150" rx="10" fill="#fff"/>';
    $svg.='<text x="48" y="435" font-family="Arial,sans-serif" font-size="12" font-weight="700" fill="'.$accent.'">SECTIONS</text>';
    for($i=0;$i<min(3,max(1,$count));$i++){ $x=48+$i*370; $svg.='<rect x="'.$x.'" y="465" width="335" height="115" rx="16" fill="#fff" stroke="#e4e7ec"/><rect x="'.($x+20).'" y="485" width="34" height="34" rx="9" fill="#eef4ff"/><text x="'.($x+75).'" y="508" font-family="Arial,sans-serif" font-size="16" font-weight="700" fill="#101828">Section '.($i+1).'</text><rect x="'.($x+20).'" y="535" width="220" height="10" rx="5" fill="#dfe5ee"/>'; }
    $svg.='<rect y="630" width="1200" height="130" fill="#101828"/><text x="48" y="682" font-family="Arial,sans-serif" font-size="18" font-weight="700" fill="#fff">'.$title.'</text><text x="48" y="712" font-family="Arial,sans-serif" font-size="12" fill="#98a2b3">'.$count.' editable blocks · lightweight template</text></svg>';
    $previewFile=$slug.'.svg';
    if(file_put_contents($imgRoot.'/'.$previewFile,$svg,LOCK_EX)===false) throw new RuntimeException('Preview otomatis tidak bisa disimpan. Cek permission folder public/assets/images/templates.');
  }

  $copied=0;
  for($i=0;$i<$zip->numFiles;$i++){
    $stat=$zip->statIndex($i); $n=(string)($stat['name']??'');
    if(strpos($n,'assets/')!==0 || substr($n,-1)==='/') continue;
    if(!template_safe_zip_name($n)) continue;
    $ext=strtolower(pathinfo($n,PATHINFO_EXTENSION));
    if(!in_array($ext,['jpg','jpeg','png','webp','gif','svg'],true)) continue;
    if((int)($stat['size']??0)>2*1024*1024 || $copied>=20) continue;
    $data=$zip->getFromIndex($i); if($data===false) continue;
    $base=basename($n); $safe=preg_replace('/[^a-zA-Z0-9._-]/','-', $base); if($safe==='') continue;
    if(file_put_contents($imgDir.'/'.$safe,$data,LOCK_EX)===false) throw new RuntimeException('Asset template tidak bisa disimpan: '.$safe.'. Cek permission folder public/assets/images/templates.');
    $copied++;
  }
  $zip->close();

  $library=get_template_library();
  $entry=['slug'=>$slug,'name'=>$name,'description'=>trim((string)($manifest['description']??'')),'version'=>trim((string)($manifest['version']??'1.0')),'css'=>$cssFile,'preview'=>$previewFile,'blocks'=>$blocks,'settings'=>$settings,'block_count'=>count($blocks)];
  $library=array_values(array_filter($library,fn($x)=>is_array($x) && ($x['slug']??'')!==$slug));
  $library[]=$entry;
  $saved=json_encode($library,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
  if($saved===false) throw new RuntimeException('Data template gagal disimpan ke JSON.');
  save_setting('template_library',$saved);

  template_json_response(['ok'=>true,'template'=>$entry,'templates'=>$library]);
} catch(Throwable $e) {
  template_json_response(['ok'=>false,'error'=>$e->getMessage()],500);
}
