<?php
declare(strict_types=1);

// Autosave endpoint must never be cached by browser/proxy layers.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// AJAX responses must remain valid JSON even when the host PHP emits a warning/notices.
// Buffer accidental diagnostic output and clear it before every JSON response.
ini_set('display_errors','0');
ini_set('log_errors','1');
ob_start();
function ara_ajax_response(array $payload, int $status=200): void {
  while(ob_get_level()>0) @ob_end_clean();
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
  exit;
}
register_shutdown_function(function(){
  $e=error_get_last();
  if($e && in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)){
    while(ob_get_level()>0) @ob_end_clean();
    if(!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Server error: '.(string)$e['message'], 'line'=>(int)$e['line']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
  }
});
require_once __DIR__.'/../app/Security.php';
require_once __DIR__.'/../app/Content.php';
require_once __DIR__.'/../app/site-template.php';
admin_required();
$raw=file_get_contents('php://input');
$in=json_decode($raw,true);
if(!is_array($in)) ara_ajax_response(['ok'=>false,'error'=>'Bad request'],400);

start_secure_session();
if(!hash_equals($_SESSION['csrf']??'', (string)($in['csrf']??''))) ara_ajax_response(['ok'=>false,'error'=>'Invalid CSRF token'],419);

$op=(string)($in['op']??'');

$BLOCK_PRESETS = array_map(function($type){ return ara_block_preset($type); }, array_keys(ara_block_registry()));

try {
  switch($op){
    case 'block_registry':
      ara_ajax_response(['ok'=>true,'blocks'=>ara_block_registry()]);

    case 'persistence_diagnostic':
      ensure_persistence_log_table();
      $pdo=Database::pdo();
      $dbPath=defined('DB_PATH')?DB_PATH:'';
      $rows=$pdo->query('SELECT id,section_key,block_type,title,typography,sort_order,is_active FROM sections ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
      $logs=$pdo->query('SELECT section_id,field,value_hash,request_uri,created_at FROM persistence_log ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
      ara_ajax_response(['ok'=>true,'db_path'=>$dbPath,'db_realpath'=>($dbPath?realpath($dbPath):false),'db_size'=>($dbPath&&is_file($dbPath)?filesize($dbPath):0),'section_count'=>count($rows),'hero_count'=>(int)$pdo->query("SELECT COUNT(*) FROM sections WHERE block_type='hero'")->fetchColumn(),'sections'=>$rows,'recent_writes'=>$logs]);

    case 'update_setting':
      $key=preg_replace('/[^a-z0-9_]/i','',(string)($in['key']??''));
      if($key==='') throw new RuntimeException('Missing key');
      $settingValue=(string)($in['value']??'');
      if($key==='typography') {
        persist_typography_json($settingValue);
        $settingValue=setting('typography','{}');
      } else {
        save_setting($key,$settingValue);
      }
      ara_ajax_response(['ok'=>true,'key'=>$key,'value'=>$settingValue,'saved_at'=>microtime(true)]);

    case 'save_slider':
      $slides=$in['slides']??[];
      if(!is_array($slides)) throw new RuntimeException('Invalid slider data');
      $clean=[];
      foreach($slides as $slide){
        if(!is_array($slide)) continue;
        $image=trim((string)($slide['image']??''));
        $alt=trim((string)($slide['alt']??''));
        if($alt==='') $alt='Slide '.(count($clean)+1);
        if($image==='') $image='';
        $clean[]=['image'=>$image,'alt'=>mb_substr($alt,0,120)];
      }
      if(count($clean)<1) throw new RuntimeException('Slider minimal punya 1 slide');
      if(count($clean)>20) $clean=array_slice($clean,0,20);
      $autoplay=((string)($in['autoplay']??'1')==='0')?'0':'1';
      $duration=max(1,min(20,(int)($in['duration']??4)));
      $transition=in_array((string)($in['transition']??'fade'),['fade','slide'],true)?(string)$in['transition']:'fade';
      $dots=((string)($in['dots']??'1')==='0')?'0':'1';
      save_setting('slider_slides',json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
      save_setting('slider_autoplay',$autoplay);
      save_setting('slider_duration',(string)$duration);
      save_setting('slider_transition',$transition);
      save_setting('slider_dots',$dots);
      ara_ajax_response(['ok'=>true,'slides'=>$clean,'autoplay'=>$autoplay,'duration'=>$duration,'transition'=>$transition,'dots'=>$dots]);

    case 'save_nav_menu':
      // Dedicated endpoint so menu persistence is isolated from generic setting/typography saves.
      $items=$in['items']??[];
      if(!is_array($items)) throw new RuntimeException('Invalid menu data');
      $clean=[];
      foreach($items as $it){
        if(!is_array($it)) continue;
        $label=trim((string)($it['label']??''));
        if($label==='') continue;
        $type=(string)($it['target_type']??'url');
        if($type==='section'){
          $sid=(int)($it['section_id']??0);
          if($sid<=0) throw new RuntimeException('Target section belum dipilih untuk: '.$label);
          $clean[]=['label'=>$label,'target_type'=>'section','section_id'=>$sid];
        }else{
          $url=trim((string)($it['url']??'#'));
          $clean[]=['label'=>$label,'target_type'=>'url','url'=>$url!==''?$url:'#'];
        }
      }
      if(!$clean) throw new RuntimeException('Menu tidak boleh kosong');
      save_setting('nav_menu',json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
      ara_ajax_response(['ok'=>true,'nav_menu'=>get_nav_menu(get_settings())]);

    case 'save_nav_typography':
      $t=$in['typography']??[];
      if(!is_array($t)) throw new RuntimeException('Invalid typography data');
      $allowedFonts=['Inter','Arial','Helvetica','Georgia','Times New Roman','Verdana','Trebuchet MS','Courier New','system-ui','Archivo Black','Bebas Neue','Caveat','Playfair Display','Poppins','Space Grotesk'];
      $clean=[];
      $font=(string)($t['font']??''); if($font!=='' && in_array($font,$allowedFonts,true)) $clean['font']=$font;
      $size=(float)($t['size']??0); if($size>=8 && $size<=60) $clean['size']=$size;
      $weight=(string)($t['weight']??''); if(in_array($weight,['400','500','600','700','800','900'],true)) $clean['weight']=$weight;
      $color=(string)($t['color']??''); if(preg_match('/^#[0-9a-fA-F]{3,8}$/',$color)) $clean['color']=$color;
      $all=json_decode(setting('typography','{}'),true); if(!is_array($all)) $all=[];
      $all['nav_menu']=$clean;
      save_setting('typography',json_encode($all,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
      ara_ajax_response(['ok'=>true,'typography'=>$clean]);

    case 'update_section_typography':
      $id=(int)($in['id']??0); $field=(string)($in['field']??'');
      if($id<=0 || $field==='') throw new RuntimeException('Missing typography target');
      $row=section_get($id); if(!$row) throw new RuntimeException('Block tidak ditemukan');
      $current=json_decode((string)($row['typography']??'{}'),true); if(!is_array($current)) $current=[];
      $cfg=$in['value']??[];
      if(!is_array($cfg)) throw new RuntimeException('Invalid typography data');
      $clean=[];
      $allowedFonts=['Inter','Arial','Helvetica','Georgia','Times New Roman','Verdana','Trebuchet MS','Courier New','system-ui','Archivo Black','Bebas Neue','Caveat','Playfair Display','Poppins','Space Grotesk'];
      $font=(string)($cfg['font']??''); if($font!=='' && in_array($font,$allowedFonts,true)) $clean['font']=$font;
      $size=(float)($cfg['size']??0); if($size>=8 && $size<=120) $clean['size']=$size;
      $weight=(string)($cfg['weight']??''); if(in_array($weight,['400','500','600','700','800','900'],true)) $clean['weight']=$weight;
      $color=(string)($cfg['color']??''); if(preg_match('/^#[0-9a-fA-F]{3,8}$/',$color)) $clean['color']=$color;
      $align=(string)($cfg['align']??''); if(in_array($align,['left','center','right','justify'],true)) $clean['align']=$align;
      if($clean) $current[$field]=$clean; else unset($current[$field]);
      $json=json_encode($current,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      section_update_field($id,'typography',$json);
      $saved=section_get($id); $savedCfg=json_decode((string)($saved['typography']??'{}'),true); if(!is_array($savedCfg)) $savedCfg=[];
      $actual=$savedCfg[$field]??[];
      if(json_encode($actual,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)!==json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) throw new RuntimeException('Typography DB verification gagal');
      ara_ajax_response(['ok'=>true,'id'=>$id,'field'=>$field,'typography'=>$actual,'saved_at'=>microtime(true)]);

    case 'update_section':
      $id=(int)($in['id']??0); $field=(string)($in['field']??'');
      if($id<=0) throw new RuntimeException('Missing id');
      if(!section_get($id)) throw new RuntimeException('Block tidak ditemukan');
      $value=$in['value']??'';
      $ok=section_update_field($id,$field,$value);
      if(!$ok) throw new RuntimeException('Unknown field');
      // Re-read from SQLite after the UPDATE so the browser gets the actual
      // authoritative value, not merely an optimistic client value.
      $row=section_get($id);
      if(!$row || !array_key_exists($field,$row)) throw new RuntimeException('Perubahan block tidak tersimpan');
      $saved=$row[$field];
      $expected=($field==='body') ? sanitize_rich_html((string)$value) : (string)$value;
      if((string)$saved !== $expected){
        throw new RuntimeException('DB menolak perubahan block (nilai server berbeda)');
      }
      ara_ajax_response(['ok'=>true,'id'=>$id,'field'=>$field,'value'=>$saved,'saved_at'=>microtime(true)]);

    case 'toggle':
      $id=(int)($in['id']??0);
      if($id<=0) throw new RuntimeException('Missing id');
      section_update_field($id,'is_active',!empty($in['active']));
      ara_ajax_response(['ok'=>true]);

    case 'reorder':
      create_revision('Sebelum mengubah urutan block');
      $order=array_values(array_filter(array_map('intval',$in['order']??[])));
      section_reorder($order);
      ara_ajax_response(['ok'=>true]);

    case 'add':
      create_revision('Sebelum menambah block');
      $type=(string)($in['block_type']??'feature');
      if($type==='hero'){ $heroCount=(int)Database::pdo()->query("SELECT COUNT(*) FROM sections WHERE block_type='hero'")->fetchColumn(); if($heroCount>0) throw new RuntimeException('Hero sudah ada. Gunakan Hero yang ada agar tidak membuat Hero ganda.'); }
      $preset=$BLOCK_PRESETS[$type]??$BLOCK_PRESETS['feature'];
      $newId=section_create(['title'=>$preset['title'],'subtitle'=>$preset['subtitle'],'body'=>$preset['body'],'layout'=>$preset['layout'],'block_type'=>$type,'button_text'=>$preset['button_text']]);
      if($type==='hero'){ save_setting('hero_deleted','0'); save_setting('hero_visible','1'); }
      $row=section_get($newId);
      if($afterId=(int)($in['after_id']??0)){
        $ids=array_column(get_all_sections(),'id');
        $ids=array_values(array_filter($ids,fn($x)=>$x!=$newId));
        $pos=array_search($afterId,$ids,true);
        if($pos!==false) array_splice($ids,$pos+1,0,[$newId]); else $ids[]=$newId;
        section_reorder($ids);
      }
      ara_ajax_response(['ok'=>true,'id'=>$newId,'html'=>render_block($row,true,true)]);

    case 'duplicate':
      create_revision('Sebelum duplikat block');
      $id=(int)($in['id']??0);
      $newId=section_duplicate($id);
      if(!$newId) throw new RuntimeException('Section not found');
      $ids=array_column(get_all_sections(),'id');
      $ids=array_values(array_filter($ids,fn($x)=>$x!=$newId));
      $pos=array_search($id,$ids,true);
      if($pos!==false) array_splice($ids,$pos+1,0,[$newId]); else $ids[]=$newId;
      section_reorder($ids);
      $row=section_get($newId);
      ara_ajax_response(['ok'=>true,'id'=>$newId,'html'=>render_block($row,true,true)]);

    case 'delete':
      create_revision('Sebelum menghapus block');
      $id=(int)($in['id']??0);
      if($id<=0) throw new RuntimeException('Missing id');
      section_delete($id);
      ara_ajax_response(['ok'=>true]);

    case 'apply_builtin_template':
      $theme=(string)($in['theme']??'default'); $layout=(string)($in['layout']??'stacked');
      apply_builtin_template($theme,$layout);
      ara_ajax_response(['ok'=>true]);

    case 'apply_template':
      $slug=preg_replace('/[^a-z0-9-]/i','',(string)($in['slug']??''));
      $tpl=get_template_by_slug($slug);
      if(!$tpl) throw new RuntimeException('Template tidak ditemukan');
      create_revision('Sebelum menerapkan template: '.((string)($tpl['name']??$slug)));
      $pdo=Database::pdo(); $pdo->beginTransaction();
      try {
        // V18: applying a template is presentation-only. Existing content rows
        // are never deleted or replaced. Matching block types receive the
        // template's layout settings and the existing order is adapted.
        $matched=apply_template_layout($tpl);
        $settings=is_array($tpl['settings']??null)?$tpl['settings']:[];
        foreach(template_design_settings() as $key){
          if(array_key_exists($key,$settings)) save_setting($key,(string)$settings[$key]);
        }
        save_setting('template_css',(string)($tpl['css']??''));
        save_setting('template_name',(string)($tpl['name']??$slug));
        save_setting('hero_deleted','0');
        $pdo->commit();
      } catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
      ara_ajax_response(['ok'=>true,'mode'=>'design','block_count'=>count((array)($tpl['blocks']??[])),'matched'=>$matched]);

    case 'import_template_content':
      $slug=preg_replace('/[^a-z0-9-]/i','',(string)($in['slug']??''));
      $tpl=get_template_by_slug($slug);
      if(!$tpl) throw new RuntimeException('Template tidak ditemukan');
      if(empty($tpl['blocks'])) throw new RuntimeException('Template ini tidak memiliki starter content');
      create_revision('Sebelum import starter content: '.((string)($tpl['name']??$slug)));
      $count=import_template_content($tpl);
      ara_ajax_response(['ok'=>true,'count'=>$count]);

    case 'restore_template_backup':
      if(!restore_last_template_layout()) throw new RuntimeException('Belum ada revision template yang bisa dipulihkan');
      ara_ajax_response(['ok'=>true]);

    case 'reset_template':
      reset_active_template();
      ara_ajax_response(['ok'=>true]);

    case 'delete_template':
      $slug=preg_replace('/[^a-z0-9-]/i','',(string)($in['slug']??''));
      if($slug==='') throw new RuntimeException('Template tidak valid');
      $library=get_template_library();
      $tpl=null; $remaining=[];
      foreach($library as $item){
        if(is_array($item) && (string)($item['slug']??'')===$slug) $tpl=$item;
        else $remaining[]=$item;
      }
      if(!$tpl) throw new RuntimeException('Template tidak ditemukan');

      $activeCss=setting('template_css','');
      $activeName=setting('template_name','');
      $isActive=((string)($tpl['css']??'')!=='' && (string)($tpl['css']??'')===$activeCss)
        || ((string)($tpl['name']??'')!=='' && (string)($tpl['name']??'')===$activeName);
      if($isActive){
        save_setting('template_css','');
        save_setting('template_name','');
        save_setting('site_theme','default');
        save_setting('hero_layout','stacked');
        save_setting('accent_color','');
      }

      $cssFile=basename((string)($tpl['css']??''));
      if($cssFile!=='' && preg_match('/^[a-z0-9._-]+$/i',$cssFile)){
        $path=__DIR__.'/../public/assets/css/templates/'.$cssFile;
        if(is_file($path)) @unlink($path);
      }
      $previewFile=basename((string)($tpl['preview']??''));
      if($previewFile!=='' && preg_match('/^[a-z0-9._-]+$/i',$previewFile)){
        $path=__DIR__.'/../public/assets/images/templates/'.$previewFile;
        if(is_file($path)) @unlink($path);
      }
      $assetDir=__DIR__.'/../public/assets/images/templates/'.$slug;
      if(is_dir($assetDir)){
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($assetDir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($it as $file){ if($file->isDir()) @rmdir($file->getPathname()); else @unlink($file->getPathname()); }
        @rmdir($assetDir);
      }

      save_setting('template_library',json_encode(array_values($remaining),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));
      ara_ajax_response(['ok'=>true,'slug'=>$slug,'templates'=>$remaining,'was_active'=>$isActive]);

    default:
      ara_ajax_response(['ok'=>false,'error'=>'Unknown operation'],400);
  }
} catch(Throwable $e) {
  ara_ajax_response(['ok'=>false,'error'=>$e->getMessage()],400);
}
