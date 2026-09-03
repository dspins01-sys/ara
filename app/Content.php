<?php
declare(strict_types=1);
require_once __DIR__ . '/Database.php';

/** V20.6.9: section typography is persisted beside the block so Live and Builder
 * always read the same authoritative value. Existing databases are migrated
 * lazily and safely. */
function ensure_section_typography_column(): void {
    static $done=false; if($done) return; $done=true;
    try {
        $pdo=Database::pdo();
        $table=$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='sections' LIMIT 1")->fetchColumn();
        if (!$table) return;
        $cols=$pdo->query("PRAGMA table_info(sections)")->fetchAll();
        $has=false; foreach($cols as $c){ if((string)($c['name']??'')==='typography') { $has=true; break; } }
        if(!$has) $pdo->exec("ALTER TABLE sections ADD COLUMN typography TEXT DEFAULT '{}' ");
    } catch(Throwable $e) { /* old SQLite/schema remains usable; global typography is fallback */ }
}
function ensure_persistence_log_table(): void {
    static $done=false; if($done) return; $done=true;
    try {
        Database::pdo()->exec("CREATE TABLE IF NOT EXISTS persistence_log(id INTEGER PRIMARY KEY AUTOINCREMENT,section_id INTEGER,field TEXT,value_hash TEXT,request_uri TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    } catch(Throwable $e) {}
}
function log_section_write(int $id,string $field,$value): void {
    ensure_persistence_log_table();
    try {
        $hash=hash('sha256',is_string($value)?$value:json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $uri=substr((string)($_SERVER['REQUEST_URI']??''),0,500);
        $pdo=Database::pdo();
        $pdo->prepare('INSERT INTO persistence_log(section_id,field,value_hash,request_uri) VALUES(?,?,?,?)')->execute([$id,$field,$hash,$uri]);
        $pdo->exec('DELETE FROM persistence_log WHERE id NOT IN (SELECT id FROM persistence_log ORDER BY id DESC LIMIT 500)');
    } catch(Throwable $e) {}
}
function section_typography_map(): array {
    ensure_section_typography_column();
    $map=[];
    try {
        foreach(Database::pdo()->query('SELECT id,typography FROM sections')->fetchAll() as $r){
            $t=json_decode((string)($r['typography']??'{}'),true);
            if(is_array($t)) foreach($t as $field=>$cfg){
                if(is_array($cfg)) $map['section.'.(int)$r['id'].'.'.$field]=$cfg;
            }
        }
    } catch(Throwable $e) {}
    return $map;
}
function persist_typography_json(string $json): void {
    $all=json_decode($json,true); if(!is_array($all)) $all=[];
    save_setting('typography',json_encode($all,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    ensure_section_typography_column();
    $pdo=Database::pdo();
    $grouped=[];
    foreach($all as $key=>$cfg){
        if(!is_array($cfg)) continue;
        if(preg_match('/^section\.(\d+)\.([a-zA-Z0-9_]+)$/',(string)$key,$m)){
            $id=(int)$m[1]; $field=(string)$m[2];
            if($id>0) $grouped[$id][$field]=$cfg;
        }
    }
    try {
        $rows=$pdo->query('SELECT id FROM sections')->fetchAll(PDO::FETCH_COLUMN);
        $st=$pdo->prepare('UPDATE sections SET typography=? WHERE id=?');
        foreach($rows as $id){
            $t=$grouped[(int)$id]??[];
            $st->execute([json_encode($t,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$id]);
        }
    } catch(Throwable $e) { /* global JSON remains authoritative fallback */ }
}

const ARA_DEFAULT_SETTINGS = [
    'site_name'=>'PT Ara DigiTalent','logo_image'=>'','logo_width'=>'52','logo_height'=>'52','header_background'=>'#1b1b1b','header_height_mode'=>'auto','header_height'=>'70','header_padding_y'=>'12','hero_visible'=>'1','hero_deleted'=>'0','hero_section_migrated'=>'0','hero_title'=>'Solusi Digital untuk Bisnis Anda','hero_text'=>'Konsultan, Kontraktor, dan Pengadaan Barang/Jasa','hero_button'=>'Mulai Konsultasi','hero_image'=>'',
    'contractor_kicker'=>'DAN JANGAN LUPA','contractor_title'=>'Kontraktor Profesional','contractor_text'=>'Kami adalah kontraktor profesional yang siap membantu Anda dalam pelaksanaan proyek konstruksi. Dengan tim yang terampil dan berpengalaman, kami menjamin kualitas dan kepuasan pelanggan. Percayakan proyek konstruksi Anda kepada kami dan rasakan hasil yang memuaskan.','contractor_image'=>'',
    'about_title'=>'Tentang PT Ara DigiTalent','about_text'=>'PT Ara DigiTalent adalah perusahaan konsultan, kontraktor, dan pengadaan barang dan jasa yang berfokus pada solusi digital. Kami memiliki tim yang berpengalaman dan ahli dalam bidang teknologi digital. Kami menyediakan layanan konsultasi, pengembangan, implementasi, dan pengadaan untuk membantu bisnis Anda tumbuh dan berkembang di era digital.',
    'digital_kicker'=>'PERTAMA-TAMA','digital_title'=>'Solusi Digital untuk Bisnis Anda','digital_text'=>'Kami adalah PT Ara DigiTalent, perusahaan yang menyediakan solusi digital untuk bisnis Anda. Dengan pengalaman dan keahlian kami, kami siap membantu Anda dalam mengembangkan bisnis Anda melalui konsultasi, kontraktor, serta pengadaan barang dan jasa. Dapatkan solusi terbaik untuk kebutuhan digital Anda bersama kami.','digital_image'=>'',
    'consulting_kicker'=>'BELUM LAGI','consulting_title'=>'Layanan Konsultasi','consulting_text'=>'Kami menyediakan layanan konsultasi yang inovatif dan terpercaya untuk membantu bisnis Anda mencapai kesuksesan. Dengan pengalaman dan pengetahuan yang luas, tim kami siap memberikan solusi terbaik untuk meningkatkan kinerja dan efisiensi bisnis Anda.','consulting_image'=>'',
    'contact_title'=>'Hubungi Kami','contact_text'=>'Ceritakan kebutuhan bisnis Anda dan tim kami akan menghubungi Anda.','contact_button'=>'Kirim','contact_email'=>'','footer_text'=>'PT Ara DigiTalent','social_facebook'=>'','social_instagram'=>'','social_x'=>'','social_linkedin'=>'','social_whatsapp'=>'',
    'nav_menu'=>'[{"label":"Beranda","url":"#top"},{"label":"Tentang","url":"#about"},{"label":"Kontak","url":"#contact"}]','slider_slides'=>'[]','slider_autoplay'=>'1','slider_duration'=>'4','slider_transition'=>'fade','slider_dots'=>'1','site_theme'=>'default','hero_layout'=>'stacked','typography'=>'{}','template_css'=>'','template_name'=>'','template_library'=>'[]'
];
/** V20 Core: one lightweight registry is the single source of truth for block types. */
function ara_block_registry(): array {
    static $registry = null;
    if ($registry !== null) return $registry;
    $registry = [
        'hero'=>['icon'=>'🚀','label'=>'Hero','description'=>'Hero utama','default_layout'=>'center','default_button'=>'Mulai Konsultasi'],
        'feature'=>['icon'=>'⬛','label'=>'Feature','description'=>'Teks + gambar berdampingan','default_layout'=>'image-right','default_button'=>''],
        'text'=>['icon'=>'▤','label'=>'Rich Text','description'=>'Konten teks penuh','default_layout'=>'center','default_button'=>''],
        'image-text'=>['icon'=>'🖼','label'=>'Image + Text','description'=>'Gambar besar dengan teks','default_layout'=>'image-right','default_button'=>''],
        'gallery'=>['icon'=>'▦','label'=>'Gallery','description'=>'Galeri gambar','default_layout'=>'full','default_button'=>''],
        'quote'=>['icon'=>'❝','label'=>'Quote','description'=>'Kutipan / testimoni','default_layout'=>'center','default_button'=>''],
        'cta'=>['icon'=>'📣','label'=>'Call To Action','description'=>'Ajakan + tombol','default_layout'=>'center','default_button'=>'Let’s Talk'],
        'spacer'=>['icon'=>'↕','label'=>'Spacer','description'=>'Jarak kosong antar block','default_layout'=>'full','default_button'=>''],
        'about'=>['icon'=>'◎','label'=>'About','description'=>'Tentang Kami','default_layout'=>'center','default_button'=>''],
        'contact'=>['icon'=>'✉','label'=>'Contact','description'=>'Hubungi Kami','default_layout'=>'center','default_button'=>'Kirim'],
    ];
    return $registry;
}
function ara_block_type_allowed(string $type): bool { return isset(ara_block_registry()[$type]); }
function ara_block_preset(string $type): array {
    $r=ara_block_registry(); $type=ara_block_type_allowed($type)?$type:'feature';
    $p=$r[$type];
    $base=[
      'hero'=>['title'=>'Solusi Digital untuk Bisnis Anda','subtitle'=>'WITH US','body'=>'<p>Konsultan, Kontraktor, dan Pengadaan Barang/Jasa</p>'],
      'feature'=>['title'=>'Feature Section','subtitle'=>'FEATURE','body'=>'<p>Write something great here…</p>'],
      'text'=>['title'=>'Rich Content','subtitle'=>'STORY','body'=>'<p>Write something great here…</p>'],
      'image-text'=>['title'=>'Image + Text','subtitle'=>'STORY','body'=>'<p>Write something great here…</p>'],
      'gallery'=>['title'=>'Gallery','subtitle'=>'GALLERY','body'=>'<p>Write something great here…</p>'],
      'quote'=>['title'=>'What clients say','subtitle'=>'QUOTE','body'=>'<blockquote>Your statement goes here.</blockquote>'],
      'cta'=>['title'=>'Ready To Grow?','subtitle'=>'LET’S WORK TOGETHER','body'=>'<p>Write something great here…</p>'],
      'spacer'=>['title'=>'','subtitle'=>'','body'=>''],
      'about'=>['title'=>'Tentang PT Ara DigiTalent','subtitle'=>'TENTANG KAMI','body'=>'<p>Ceritakan tentang perusahaan Anda di sini.</p>'],
      'contact'=>['title'=>'Mari Bangun Sesuatu Yang Hebat','subtitle'=>'HUBUNGI KAMI','body'=>'<p>Ceritakan kebutuhan bisnis Anda dan tim kami akan menghubungi Anda.</p>'],
    ];
    $x=$base[$type]??$base['feature'];
    $x['layout']=$p['default_layout']; $x['button_text']=$p['default_button']; $x['button_url']=$type==='hero'||$type==='cta'?'#contact':'';
    return $x;
}

function get_slider_slides(array $s): array {
    $raw=(string)($s['slider_slides']??'[]');
    $slides=json_decode($raw,true);
    if(!is_array($slides) || !$slides){
        $slides=[];
        foreach([['hero_image','Hero'],['contractor_image','Kontraktor'],['digital_image','Digital'],['consulting_image','Konsultasi']] as $pair){
            $file=(string)($s[$pair[0]]??'');
            if($file!=='') $slides[]=['image'=>$file,'alt'=>$pair[1]];
        }
        if(!$slides) $slides=[['image'=>'','alt'=>'Slide 1'],['image'=>'','alt'=>'Slide 2'],['image'=>'','alt'=>'Slide 3']];
    }
    $out=[]; foreach($slides as $i=>$slide){
        if(!is_array($slide)) continue;
        $img=(string)($slide['image']??'');
        $out[]=['image'=>$img,'alt'=>trim((string)($slide['alt']??('Slide '.($i+1)))) ?: ('Slide '.($i+1))];
    }
    return array_values($out);
}

function get_nav_menu(array $s): array {
    $items=json_decode((string)($s['nav_menu']??''),true);
    if(!is_array($items) || !$items) $items=[['label'=>'Beranda','target_type'=>'section','section_id'=>0,'url'=>'#top'],['label'=>'Tentang','target_type'=>'section','section_id'=>0,'url'=>'#about'],['label'=>'Kontak','target_type'=>'section','section_id'=>0,'url'=>'#contact']];
    $sections=get_all_sections(); $byId=[]; foreach($sections as $sec){ $byId[(int)$sec['id']]=$sec; }
    $out=[];
    foreach($items as $it){
        if(!is_array($it) || !isset($it['label'])) continue;
        $type=(string)($it['target_type']??''); $sid=(int)($it['section_id']??0); $url=(string)($it['url']??'#');
        // Backward compatibility: convert legacy #anchor menu URLs into real section targets when possible.
        if($type==='' && str_starts_with($url,'#')){
            $needle=ltrim($url,'#');
            foreach($byId as $candidate){ if((string)($candidate['anchor_id']??'')===$needle){ $type='section'; $sid=(int)$candidate['id']; break; } }
        }
        if($type==='section' && $sid>0 && isset($byId[$sid])) $url='#'.ltrim((string)($byId[$sid]['anchor_id']??('block-'.$sid)),'#');
        $out[]=['label'=>(string)$it['label'],'url'=>$url,'target_type'=>$type ?: (($sid>0)?'section':'url'),'section_id'=>$sid,'orphaned'=>($type==='section' && $sid>0 && !isset($byId[$sid]))];
    }
    return $out;
}
function ensure_default_settings(): void { $pdo=Database::pdo(); $st=$pdo->prepare('INSERT OR IGNORE INTO settings(key,value) VALUES(?,?)'); foreach(ARA_DEFAULT_SETTINGS as $k=>$v)$st->execute([$k,$v]); }
function get_settings(): array { ensure_default_settings(); if(!isset($GLOBALS['__ara_settings_cache']) || !is_array($GLOBALS['__ara_settings_cache'])) $GLOBALS['__ara_settings_cache']=Database::pdo()->query('SELECT key,value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR); return $GLOBALS['__ara_settings_cache']; }
function setting(string $key,string $fallback=''): string { $s=get_settings(); return $s[$key]??$fallback; }
function save_setting(string $key,string $value): void { $st=Database::pdo()->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value'); $st->execute([$key,$value]); if(isset($GLOBALS['__ara_settings_cache']) && is_array($GLOBALS['__ara_settings_cache'])) $GLOBALS['__ara_settings_cache'][$key]=$value; }

function get_template_library(): array {
    $library=json_decode(setting('template_library','[]'),true);
    return is_array($library)?array_values(array_filter($library,fn($x)=>is_array($x))):[];
}
function get_template_by_slug(string $slug): ?array {
    foreach(get_template_library() as $t){ if((string)($t['slug']??'')===$slug) return $t; }
    return null;
}
function template_allowed_settings(): array {
    return ['site_name','logo_image','logo_width','logo_height','header_background','header_height_mode','header_height','header_padding_y','hero_title','hero_accent','hero_text','hero_button','hero_button_url','hero_badge','hero_image','contractor_image','digital_image','consulting_image','hero_layout','about_title','about_text','contact_title','contact_text','contact_button','footer_text','social_facebook','social_instagram','social_x','social_linkedin','social_whatsapp','accent_color','site_theme'];
}
function template_design_settings(): array {
    return ['site_theme','hero_layout','accent_color','logo_width','logo_height','header_background','header_height_mode','header_height','header_padding_y','typography'];
}
function apply_template_layout(array $tpl): int {
    $pdo=Database::pdo(); $current=get_all_sections(); $unused=$current; $ordered=[]; $matched=0;
    foreach((array)($tpl['blocks']??[]) as $tb){
        if(!is_array($tb)) continue;
        $type=(string)($tb['block_type']??''); if($type==='') continue;
        $found=-1;
        foreach($unused as $i=>$sec){ if((string)($sec['block_type']??'')===$type){ $found=$i; break; } }
        if($found<0) continue;
        $sec=$unused[$found]; unset($unused[$found]); $unused=array_values($unused);
        $id=(int)$sec['id'];
        $layout=in_array(($tb['layout']??''),['image-right','image-left','center','full'],true)?$tb['layout']:(string)($sec['layout']??'image-right');
        $st=$pdo->prepare('UPDATE sections SET layout=?,bg_color=?,text_color=?,padding_top=?,padding_bottom=?,max_width=?,custom_class=? WHERE id=?');
        $st->execute([$layout,trim((string)($tb['bg_color']??$sec['bg_color']??'')),trim((string)($tb['text_color']??$sec['text_color']??'')),max(0,(int)($tb['padding_top']??$sec['padding_top']??108)),max(0,(int)($tb['padding_bottom']??$sec['padding_bottom']??108)),min(1800,max(320,(int)($tb['max_width']??$sec['max_width']??900))),preg_replace('/[^a-zA-Z0-9_ -]/','',(string)($tb['custom_class']??$sec['custom_class']??'')),$id]);
        $ordered[]=$id; $matched++;
    }
    foreach($unused as $sec) $ordered[]=(int)$sec['id'];
    if($ordered) section_reorder($ordered);
    return $matched;
}
function import_template_content(array $tpl): int {
    $count=0;
    foreach((array)($tpl['blocks']??[]) as $block){ if(!is_array($block)) continue; section_create($block); $count++; }
    if($count){ $ids=array_column(get_all_sections(),'id'); section_reorder(array_map('intval',$ids)); }
    return $count;
}
function apply_builtin_template(string $theme,string $layout): void {
    $themes=['default','minimal','bold']; $layouts=['stacked','split','overlay','grid','slider'];
    if(!in_array($theme,$themes,true) || !in_array($layout,$layouts,true)) throw new RuntimeException('Template bawaan tidak valid');
    create_revision('Sebelum menerapkan template bawaan: '.$theme.' / '.$layout);

    // V20.6.3 Image Bridge:
    // The real hero image now lives on the migrated hero SECTION. The old
    // settings.hero_image value is only a legacy mirror, so bridging only
    // that setting causes the visual editor/live hero to keep the old image.
    // Keep both values in sync when crossing Normal <-> Slider.
    $settings=get_settings();
    $heroSection=null;
    foreach(get_all_sections() as $sec){
        if((string)($sec['block_type']??'')==='hero'){ $heroSection=$sec; break; }
    }
    $currentLayout=(string)($settings['hero_layout']??'');

    if($layout==='slider'){
        // Normal -> Slider: Slide 1 must come from the CURRENT hero section,
        // not contractor/digital/consulting legacy images. Extra slider slides
        // are preserved when they already exist.
        $hero=$heroSection ? trim((string)($heroSection['image']??'')) : '';
        if($hero==='') $hero=trim((string)($settings['hero_image']??''));

        $rawSlider=trim((string)($settings['slider_slides']??''));
        $decoded=$rawSlider!=='' ? json_decode($rawSlider,true) : null;
        $hasStoredSlides=is_array($decoded) && count($decoded)>0;

        if(!$hasStoredSlides){
            $slides=[];
            if($hero!=='') $slides[]=['image'=>$hero,'alt'=>'Slide 1'];
            foreach([['contractor_image','Kontraktor'],['digital_image','Digital'],['consulting_image','Konsultasi']] as $pair){
                $file=trim((string)($settings[$pair[0]]??''));
                if($file!=='' && !in_array($file,array_column($slides,'image'),true)) $slides[]=['image'=>$file,'alt'=>$pair[1]];
            }
            if(!$slides) $slides=[['image'=>'','alt'=>'Slide 1']];
            save_setting('slider_slides',json_encode($slides,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        } else {
            // If the user is entering Slider from a normal template, the
            // current normal hero is the canonical Slide 1. Keep Slide 2+ intact.
            if($currentLayout!=='slider' && $hero!==''){
                $slides=$decoded;
                if(!isset($slides[0]) || !is_array($slides[0])) $slides[0]=[];
                $slides[0]['image']=$hero;
                $slides[0]['alt']=trim((string)($slides[0]['alt']??'')) ?: 'Slide 1';
                save_setting('slider_slides',json_encode(array_values($slides),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            }
        }
        if($hero!=='') save_setting('hero_image',$hero);
    } elseif($currentLayout==='slider') {
        // Slider -> Normal: Slide 1 becomes the CURRENT hero section image too.
        // Updating only settings.hero_image is not enough after V17 migration.
        $slides=get_slider_slides($settings);
        $slide1=!empty($slides) ? trim((string)($slides[0]['image']??'')) : '';
        if($slide1!==''){
            save_setting('hero_image',$slide1);
            if($heroSection){
                section_update_field((int)$heroSection['id'],'image',$slide1);
            }
        }
    }

    save_setting('site_theme',$theme); save_setting('hero_layout',$layout);
    save_setting('template_css',''); save_setting('template_name','');
}
function reset_active_template(): void {
    create_revision('Sebelum reset template ke Default');
    save_setting('site_theme','default'); save_setting('hero_layout','stacked'); save_setting('accent_color','');
    save_setting('template_css',''); save_setting('template_name','');
}
function template_backup_current(): void {
    // V18 compatibility shim: backup means a safe presentation revision.
    create_revision('Backup template (V18)');
}
function template_restore_backup(): bool {
    // V18 compatibility shim: restore presentation only; never restore content.
    return restore_last_template_layout();
}
function sanitize_rich_html(string $html): string {
    $html=trim($html);
    $html=preg_replace('/<\/?(script|style|iframe|object|embed|form|input|button|textarea|select)[^>]*>/is','',$html);
    $html=preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$html);
    $html=preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i','$1=$2#$2',$html);
    $html=strip_tags($html,'<p><br><strong><b><em><i><u><s><h2><h3><h4><ul><ol><li><blockquote><a><img><div><span><hr>');
    return $html;
}

/**
 * Legacy compatibility only.
 *
 * Older builds used this on every page request, which meant deleting one of
 * the legacy default blocks caused it to be recreated on the next public
 * request. A CMS must treat the sections table as the source of truth.
 *
 * We intentionally do NOT seed/recreate sections here. Existing installs keep
 * their current section state, including intentional deletions. Fresh installs
 * receive their initial sections from database/schema.sql.
 */
function ensure_default_sections(): void { return; }
function e_html(string $s): string { return htmlspecialchars($s,ENT_QUOTES,'UTF-8'); }

/**
 * One-time migration of the legacy hardcoded About and Contact areas into the
 * sections table. This is deliberately one-time: after the migration a user
 * may delete either section and it must NOT be recreated on the next request.
 */
function migrate_legacy_global_sections(): void {
    static $done=false;
    if($done) return;
    $done=true;
    try {
        if(setting('global_sections_migrated','')==='1') return;
        $pdo=Database::pdo();
        $existing=$pdo->query("SELECT block_type FROM sections WHERE block_type IN ('about','contact')")->fetchAll(PDO::FETCH_COLUMN);
        $hasAbout=in_array('about',$existing,true);
        $hasContact=in_array('contact',$existing,true);
        $settings=get_settings();
        $max=(int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM sections')->fetchColumn();
        if(!$hasAbout){
            section_create([
                'title'=>$settings['about_title']??'Tentang PT Ara DigiTalent',
                'subtitle'=>'TENTANG KAMI',
                'body'=>'<p>'.e_html((string)($settings['about_text']??'PT Ara DigiTalent adalah perusahaan konsultan, kontraktor, dan pengadaan barang dan jasa.')).'</p>',
                'layout'=>'center','block_type'=>'about','is_active'=>1,'sort_order'=>$max+1
            ]);
            $max++;
        }
        if(!$hasContact){
            section_create([
                'title'=>$settings['contact_title']??'Mari Bangun Sesuatu Yang Hebat',
                'subtitle'=>'HUBUNGI KAMI',
                'body'=>'<p>'.e_html((string)($settings['contact_text']??'Ceritakan kebutuhan bisnis Anda dan tim kami akan menghubungi Anda.')).'</p>',
                'layout'=>'center','block_type'=>'contact','button_text'=>$settings['contact_button']??'Kirim','is_active'=>1,'sort_order'=>$max+1
            ]);
        }
        save_setting('global_sections_migrated','1');
    } catch(Throwable $e) {
        // Do not break the public site if an older/partially upgraded DB is in use.
    }
}

function get_sections(): array { ensure_section_typography_column(); migrate_legacy_hero_section(); migrate_legacy_global_sections(); return Database::pdo()->query('SELECT * FROM sections WHERE is_active=1 ORDER BY sort_order,id')->fetchAll(); }
function get_all_sections(): array { ensure_section_typography_column(); migrate_legacy_hero_section(); migrate_legacy_global_sections(); return Database::pdo()->query('SELECT * FROM sections ORDER BY sort_order,id')->fetchAll(); }
function migrate_section_columns(): void {
    static $done=false; if($done)return; $pdo=Database::pdo();
    $cols=$pdo->query('PRAGMA table_info(sections)')->fetchAll(PDO::FETCH_ASSOC); $names=array_column($cols,'name');
    $defs=["anchor_id TEXT DEFAULT ''","image2 TEXT DEFAULT ''","image3 TEXT DEFAULT ''","button_text TEXT DEFAULT ''","button_url TEXT DEFAULT ''","bg_color TEXT DEFAULT ''","text_color TEXT DEFAULT ''","padding_top INTEGER DEFAULT 108","padding_bottom INTEGER DEFAULT 108","max_width INTEGER DEFAULT 900","custom_class TEXT DEFAULT ''","typography TEXT DEFAULT '{}'"];
    foreach($defs as $def){[$name]=preg_split('/\s+/',$def,2); if(!in_array($name,$names,true))$pdo->exec('ALTER TABLE sections ADD COLUMN '.$def);}
    $done=true;
}
migrate_section_columns();
function migrate_section_anchors(): void {
    static $done=false; if($done)return; $pdo=Database::pdo();
    try {
        $rows=$pdo->query('SELECT id,section_key,block_type,anchor_id FROM sections ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $used=[]; foreach($rows as $r){ $a=trim((string)($r['anchor_id']??'')); if($a!=='') $used[$a]=true; }
        foreach($rows as $r){
            if(trim((string)($r['anchor_id']??''))!=='') continue;
            $type=(string)($r['block_type']??'');
            if($type==='hero') $base='top'; elseif($type==='about') $base='about'; elseif($type==='contact') $base='contact'; else {
                $base=preg_replace('/[^a-z0-9_-]+/i','-',strtolower((string)($r['section_key']??'')));
                $base=trim((string)$base,'-'); if($base==='') $base='block-'.(int)$r['id'];
            }
            $a=$base; $n=2; while(isset($used[$a])) $a=$base.'-'.$n++;
            $pdo->prepare('UPDATE sections SET anchor_id=? WHERE id=?')->execute([$a,(int)$r['id']]); $used[$a]=true;
        }
    } catch(Throwable $e) {}
    $done=true;
}
migrate_section_anchors();

/** V18 content architecture: revisions are immutable snapshots used for safe
 * template/layout operations. Restoring a template NEVER restores content.
 */
function migrate_cms_core(): void {
    static $done=false; if($done) return; $pdo=Database::pdo();
    $pdo->exec('CREATE TABLE IF NOT EXISTS revisions(id INTEGER PRIMARY KEY AUTOINCREMENT,label TEXT NOT NULL,snapshot TEXT NOT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $done=true;
}
migrate_cms_core();
function create_revision(string $label): int {
    $settings=get_settings(); $sections=get_all_sections();
    $snapshot=['settings'=>$settings,'sections'=>$sections,'created_at'=>date('c')];
    $st=Database::pdo()->prepare('INSERT INTO revisions(label,snapshot) VALUES(?,?)');
    $st->execute([$label,json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)]);
    $id=(int)Database::pdo()->lastInsertId();
    save_setting('last_template_revision',(string)$id);
    return $id;
}
function restore_revision_layout(int $id): bool {
    $st=Database::pdo()->prepare('SELECT snapshot FROM revisions WHERE id=?'); $st->execute([$id]); $raw=$st->fetchColumn();
    $snap=json_decode((string)$raw,true); if(!is_array($snap)) return false;
    $pdo=Database::pdo(); $pdo->beginTransaction();
    try {
        $allowedSettings=['template_css','template_name','site_theme','hero_layout','accent_color','typography','logo_width'];
        foreach($allowedSettings as $k){ if(array_key_exists($k,$snap['settings']??[])) save_setting($k,(string)$snap['settings'][$k]); }
        $byKey=[]; foreach(($snap['sections']??[]) as $r){ if(is_array($r) && !empty($r['section_key'])) $byKey[(string)$r['section_key']]=$r; }
        $current=get_all_sections();
        $order=[];
        foreach($current as $r){
            $key=(string)$r['section_key']; $old=$byKey[$key]??null;
            if($old){
                $st2=$pdo->prepare('UPDATE sections SET layout=?,bg_color=?,text_color=?,padding_top=?,padding_bottom=?,max_width=?,custom_class=?,is_active=? WHERE id=?');
                $st2->execute([(string)($old['layout']??'image-right'),(string)($old['bg_color']??''),(string)($old['text_color']??''),(int)($old['padding_top']??108),(int)($old['padding_bottom']??108),(int)($old['max_width']??900),(string)($old['custom_class']??''),(int)($old['is_active']??1),(int)$r['id']]);
                $order[]=(int)$r['id'];
            }
        }
        if($order) section_reorder($order);
        $pdo->commit(); return true;
    } catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
function restore_last_template_layout(): bool {
    $id=(int)setting('last_template_revision','0');
    if($id<=0) return false;
    return restore_revision_layout($id);
}

/** Convert the legacy global Hero into a normal section exactly once. If a
 * previous build explicitly deleted Hero, respect that decision and do not
 * resurrect it. */
function migrate_legacy_hero_section(): void {
    static $done=false; if($done) return; $done=true;
    try {
        if(setting('hero_section_migrated','')==='1') return;
        $pdo=Database::pdo();
        $exists=(int)$pdo->query("SELECT COUNT(*) FROM sections WHERE block_type='hero'")->fetchColumn();
        if($exists===0 && setting('hero_deleted','0')!=='1'){
            $s=get_settings();
            $max=(int)$pdo->query('SELECT COALESCE(MAX(sort_order),-1) FROM sections')->fetchColumn();
            section_create([
                'title'=>$s['hero_title']??'Solusi Digital untuk Bisnis Anda',
                'subtitle'=>$s['hero_accent']??'WITH US',
                'body'=>'<p>'.e_html((string)($s['hero_text']??'Konsultan, Kontraktor, dan Pengadaan Barang/Jasa')).'</p>',
                'image'=>$s['hero_image']??'', 'image2'=>$s['digital_image']??'', 'image3'=>$s['contractor_image']??'',
                'layout'=>'center','block_type'=>'hero','button_text'=>$s['hero_button']??'','button_url'=>$s['hero_button_url']??'#contact',
                'sort_order'=>$max+1,'is_active'=>(int)(($s['hero_visible']??'1')!=='0')
            ]);
        }
        $heroId=(int)$pdo->query("SELECT id FROM sections WHERE block_type='hero' ORDER BY id DESC LIMIT 1")->fetchColumn();
        if($heroId>0){ $rows=$pdo->query("SELECT id FROM sections WHERE id<>$heroId ORDER BY sort_order,id")->fetchAll(PDO::FETCH_COLUMN); array_unshift($rows,$heroId); section_reorder(array_map('intval',$rows)); }
        save_setting('hero_section_migrated','1');
    } catch(Throwable $e) { /* legacy DBs remain usable */ }
}
function section_update(int $id,array $data): void {
    $pdo=Database::pdo(); $st=$pdo->prepare('UPDATE sections SET anchor_id=?,title=?,subtitle=?,body=?,image=?,image2=?,image3=?,layout=?,block_type=?,button_text=?,button_url=?,bg_color=?,text_color=?,padding_top=?,padding_bottom=?,max_width=?,custom_class=?,is_active=? WHERE id=?');
    $st->execute([trim((string)($data['anchor_id']??($data['section_key']??''))),trim((string)($data['title']??'')),trim((string)($data['subtitle']??'')),sanitize_rich_html((string)($data['body']??'')),trim((string)($data['image']??'')),trim((string)($data['image2']??'')),trim((string)($data['image3']??'')),$data['layout']??'image-right',$data['block_type']??'feature',trim((string)($data['button_text']??'')),trim((string)($data['button_url']??'')),trim((string)($data['bg_color']??'')),trim((string)($data['text_color']??'')),max(0,(int)($data['padding_top']??108)),max(0,(int)($data['padding_bottom']??108)),min(1800,max(320,(int)($data['max_width']??900))),preg_replace('/[^a-zA-Z0-9_ -]/','',(string)($data['custom_class']??'')),(int)($data['is_active']??1),$id]);
}
function section_create(array $data): int {
    $pdo=Database::pdo(); $max=(int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM sections')->fetchColumn(); $title=trim((string)($data['title']??'New Section')); $key=preg_replace('/[^a-z0-9_-]+/i','-',strtolower($title)).'-'.bin2hex(random_bytes(3));
    $st=$pdo->prepare('INSERT INTO sections(section_key,anchor_id,title,subtitle,body,image,image2,image3,sort_order,is_active,layout,block_type,button_text,button_url,bg_color,text_color,padding_top,padding_bottom,max_width,custom_class,typography) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $sort=array_key_exists('sort_order',$data)?(int)$data['sort_order']:$max+1; $anchor=trim((string)($data['anchor_id']??'')); if($anchor==='') { $anchor=($data['block_type']??'feature')==='hero'?'top':(($data['block_type']??'feature')==='about'?'about':(($data['block_type']??'feature')==='contact'?'contact':trim((string)preg_replace('/[^a-z0-9_-]+/i','-',strtolower($key)),'-'))); }
    if($anchor==='') $anchor='block-'.bin2hex(random_bytes(2));
        $st->execute([$key,$anchor,$title,$data['subtitle']??'YOUR EYEBROW',$data['body']??'<p>Write something great here…</p>',$data['image']??'',$data['image2']??'',$data['image3']??'',$sort,(int)($data['is_active']??1),$data['layout']??'image-right',$data['block_type']??'feature',$data['button_text']??'',$data['button_url']??'',$data['bg_color']??'',$data['text_color']??'',max(0,(int)($data['padding_top']??108)),max(0,(int)($data['padding_bottom']??108)),min(1800,max(320,(int)($data['max_width']??900))),preg_replace('/[^a-zA-Z0-9_ -]/','',(string)($data['custom_class']??'')),(string)($data['typography']??'{}')]);
    return (int)$pdo->lastInsertId();
}
function section_delete(int $id): void { Database::pdo()->prepare('DELETE FROM sections WHERE id=?')->execute([$id]); }
function section_reorder(array $ids): void { $pdo=Database::pdo(); $st=$pdo->prepare('UPDATE sections SET sort_order=? WHERE id=?'); $n=0; foreach($ids as $id){$st->execute([$n++,(int)$id]);} }
function section_get(int $id): ?array { $st=Database::pdo()->prepare('SELECT * FROM sections WHERE id=?'); $st->execute([$id]); $r=$st->fetch(); return $r?:null; }

const SECTION_FIELD_TYPES=['anchor_id'=>'anchor','title'=>'text','subtitle'=>'text','body'=>'html','image'=>'text','image2'=>'text','image3'=>'text','layout'=>'layout','block_type'=>'block_type','button_text'=>'text','button_url'=>'text','bg_color'=>'text','text_color'=>'text','padding_top'=>'int','padding_bottom'=>'int','max_width'=>'width','custom_class'=>'class','is_active'=>'bool','typography'=>'typography'];

/** Update exactly one whitelisted field on a section. Returns false for unknown fields (no-op, safe). */
function section_update_field(int $id,string $field,$value): bool {
    if(!array_key_exists($field,SECTION_FIELD_TYPES)) return false;
    switch(SECTION_FIELD_TYPES[$field]){
        case 'html': $value=sanitize_rich_html((string)$value); break;
        case 'int': $value=max(0,(int)$value); break;
        case 'width': $value=min(1800,max(320,(int)$value)); break;
        case 'bool': $value=(int)((bool)$value); break;
        case 'anchor': $value=preg_replace('/[^a-z0-9_-]/i','-',strtolower(trim((string)$value))); $value=trim($value,'-'); if($value==='') $value='block-'.$id; break;
        case 'class': $value=preg_replace('/[^a-zA-Z0-9_ -]/','',(string)$value); break;
        case 'layout': $value=in_array($value,['image-right','image-left','center','full'],true)?$value:'image-right'; break;
        case 'block_type': $value=ara_block_type_allowed((string)$value)?(string)$value:'feature'; break;
        case 'typography':
            $raw=is_string($value)?$value:json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $cfg=json_decode((string)$raw,true); if(!is_array($cfg)) $cfg=[];
            $clean=[];
            $allowedFonts=['Inter','Arial','Helvetica','Georgia','Times New Roman','Verdana','Trebuchet MS','Courier New','system-ui','Archivo Black','Bebas Neue','Caveat','Playfair Display','Poppins','Space Grotesk'];
            foreach($cfg as $fk=>$fv){
                if(!is_array($fv)) continue; $x=[];
                $font=(string)($fv['font']??''); if($font!=='' && in_array($font,$allowedFonts,true)) $x['font']=$font;
                $size=(float)($fv['size']??0); if($size>=8 && $size<=120) $x['size']=$size;
                $weight=(string)($fv['weight']??''); if(in_array($weight,['400','500','600','700','800','900'],true)) $x['weight']=$weight;
                $color=(string)($fv['color']??''); if(preg_match('/^#[0-9a-fA-F]{3,8}$/',$color)) $x['color']=$color;
                $align=(string)($fv['align']??''); if(in_array($align,['left','center','right','justify'],true)) $x['align']=$align;
                if($x) $clean[(string)$fk]=$x;
            }
            $value=json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); break;
        default: $value=trim((string)$value);
    }
    Database::pdo()->prepare("UPDATE sections SET {$field}=? WHERE id=?")->execute([$value,$id]);
    log_section_write($id,$field,$value);
    return true;
}
function section_duplicate(int $id): ?int {
    $row=section_get($id); if(!$row) return null;
    return section_create(['title'=>trim((string)($row['title']?:'Untitled')).' Copy','subtitle'=>$row['subtitle']??'','body'=>$row['body']??'','image'=>$row['image']??'','image2'=>$row['image2']??'','image3'=>$row['image3']??'','anchor_id'=>trim((string)($row['anchor_id']??'')).'-copy','layout'=>$row['layout']??'image-right','block_type'=>$row['block_type']??'feature','button_text'=>$row['button_text']??'','button_url'=>$row['button_url']??'','bg_color'=>$row['bg_color']??'','text_color'=>$row['text_color']??'','padding_top'=>$row['padding_top']??108,'padding_bottom'=>$row['padding_bottom']??108,'max_width'=>$row['max_width']??900,'custom_class'=>$row['custom_class']??'','typography'=>$row['typography']??'{}']);
}
