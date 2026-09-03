<?php
declare(strict_types=1);

/** Cache-busting version string for a static asset, based on its on-disk mtime.
 *  Falls back to time() if the file can't be stat'd, so a missing file never
 *  causes a fatal error — it just won't cache-bust reliably that one request. */
function ara_asset_v(string $absPath): string {
  $t = @filemtime($absPath);
  return (string)($t !== false ? $t : time());
}
/** Builds a data-cms-key (+ optional data-cms-html) attribute string, but ONLY in edit mode. */
function ara_typography_css(string $key): string {
  $all=$GLOBALS['__ara_typography']??[];
  $sectionMap=$GLOBALS['__ara_section_typography']??[];
  $t=is_array($sectionMap) && isset($sectionMap[$key]) && is_array($sectionMap[$key]) ? $sectionMap[$key] : (is_array($all) && isset($all[$key]) && is_array($all[$key]) ? $all[$key] : []);
  $allowedFonts=['Inter','Arial','Helvetica','Georgia','Times New Roman','Verdana','Trebuchet MS','Courier New','system-ui','Archivo Black','Bebas Neue','Caveat','Playfair Display','Poppins','Space Grotesk'];
  $font=(string)($t['font']??'');
  $css=[];
  if(in_array($font,$allowedFonts,true)) $css[]='font-family:'.e($font).' !important';
  $size=(float)($t['size']??0); if($size>=8 && $size<=120) $css[]='font-size:'.rtrim(rtrim((string)$size,'0'),'.').'px !important';
  $weight=(string)($t['weight']??''); if(in_array($weight,['400','500','600','700','800','900'],true)) $css[]='font-weight:'.$weight.' !important';
  $color=(string)($t['color']??''); if(preg_match('/^#[0-9a-fA-F]{3,8}$/',$color)) $css[]='color:'.e($color).' !important';
  $align=(string)($t['align']??''); if(in_array($align,['left','center','right','justify'],true)) $css[]='text-align:'.$align.' !important';
  return implode(';',$css);
}
/** Marks an editable field and applies its saved typography without changing the content model. */
function ara_nav_typography_css(): string {
  $all=$GLOBALS['__ara_typography']??[];
  $t=is_array($all) && isset($all['nav_menu']) && is_array($all['nav_menu']) ? $all['nav_menu'] : [];
  $allowedFonts=['Inter','Arial','Helvetica','Georgia','Times New Roman','Verdana','Trebuchet MS','Courier New','system-ui','Archivo Black','Bebas Neue','Caveat','Playfair Display','Poppins','Space Grotesk'];
  $css=[];
  $font=(string)($t['font']??''); if(in_array($font,$allowedFonts,true)) $css[]='font-family:'.e($font).' !important';
  $size=(float)($t['size']??0); if($size>=8 && $size<=60) $css[]='font-size:'.rtrim(rtrim((string)$size,'0'),'.').'px !important';
  $weight=(string)($t['weight']??''); if(in_array($weight,['400','500','600','700','800','900'],true)) $css[]='font-weight:'.$weight.' !important';
  $color=(string)($t['color']??''); if(preg_match('/^#[0-9a-fA-F]{3,8}$/',$color)) $css[]='color:'.e($color).' !important';
  return implode(';',$css);
}
function ck(bool $editMode,string $key,bool $html=false): string {
  $css=ara_typography_css($key);
  if(!$editMode) return $css?' style="'.$css.'"':'';
  return ' data-cms-key="'.e($key).'"'.($html?' data-cms-html="1"':'').($css?' style="'.$css.'"':'');
}
function ara_img_url(string $file,string $fallback,bool $preview): string {
  if(str_starts_with($file,'templates/')) return $preview?'../public/assets/images/'.implode('/',array_map('rawurlencode',explode('/',$file))):'/assets/images/'.implode('/',array_map('rawurlencode',explode('/',$file)));
  return $file?($preview?'../public/uploads/'.rawurlencode($file):'/uploads/'.rawurlencode($file)):($preview?'../public/assets/images/'.$fallback:'/assets/images/'.$fallback);
}
function ara_img(string $file,string $fallback,bool $preview,string $alt,string $editKey='',bool $editMode=false): string {
  $src=ara_img_url($file,$fallback,$preview);
  $attr=($editMode && $editKey)?' data-cms-image="'.e($editKey).'" class="cms-img"':'';
  return '<img src="'.e($src).'" alt="'.e($alt).'"'.$attr.'>';
}
/** Builds the hero visual markup (image, thumb, 2x2 grid, or auto slider) depending on hero_layout.
 *  Shared by render_site() and admin/theme-thumb.php so live previews match the real page exactly. */
function ara_hero_visual(string $heroLayout,array $s,bool $preview,bool $editMode=false): string {
  $raw=static fn(string $k,string $f=''):string=>(string)($s[$k]??$f);
  if($heroLayout==='grid'){
    ob_start(); ?>
<div class="hero-image-wrap hero-grid">
<?=ara_img($raw('hero_image'),'hero.jpg',$preview,'Hero','hero_image',$editMode)?>
<?=ara_img($raw('contractor_image'),'contractor.jpg',$preview,'Kontraktor','contractor_image',$editMode)?>
<?=ara_img($raw('digital_image'),'team.jpg',$preview,'Digital','digital_image',$editMode)?>
<?=ara_img($raw('consulting_image'),'contractor.jpg',$preview,'Konsultasi','consulting_image',$editMode)?>
</div>
    <?php return ob_get_clean();
  }
  if($heroLayout==='slider'){
    $slides=get_slider_slides($s);
    $count=max(1,count($slides));
    $sliderAutoplay=((string)($s['slider_autoplay']??'1')==='0')?'0':'1';
    $sliderDuration=max(1,min(20,(int)($s['slider_duration']??4)));
    $sliderTransition=in_array((string)($s['slider_transition']??'fade'),['fade','slide'],true)?(string)$s['slider_transition']:'fade';
    $sliderDots=((string)($s['slider_dots']??'1')==='0')?'0':'1';
    ob_start(); ?>
<div class="hero-image-wrap hero-slider hero-slider-<?=e($sliderTransition)?>" data-slider-count="<?=$count?>" data-slider-autoplay="<?=$sliderAutoplay?>" data-slider-duration="<?=$sliderDuration?>" data-slider-transition="<?=e($sliderTransition)?>" data-slider-dots="<?=$sliderDots?>">
<?php foreach($slides as $i=>$slide): $idx=$i+1; $img=(string)($slide['image']??''); $alt=(string)($slide['alt']??('Slide '.$idx)); ?>
<div class="hero-slide<?=$i===0?' is-active':''?>" aria-hidden="<?=$i===0?'false':'true'?>" style="--slide-index:<?=$idx?>"><?=ara_img($img,'hero.jpg',$preview,$alt,$editMode?'slider_slides.'.$i:'',$editMode)?></div>
<?php endforeach; ?>
</div>
    <?php return ob_get_clean();
  }
  ob_start(); ?>
<div class="hero-image-wrap"><?=ara_img($raw('hero_image'),'hero.jpg',$preview,'Hero','hero_image',$editMode)?><div class="hero-thumb"><?=ara_img($raw('digital_image'),'team.jpg',$preview,'Preview','digital_image',$editMode)?></div></div>
  <?php return ob_get_clean();
}
function ara_block_style(array $sec): string {
  $x='';
  if(!empty($sec['bg_color']))$x.='background:'.e($sec['bg_color']).';';
  if(!empty($sec['text_color']))$x.='color:'.e($sec['text_color']).';';
  if(isset($sec['padding_top']))$x.='padding-top:'.(int)$sec['padding_top'].'px;';
  if(isset($sec['padding_bottom']))$x.='padding-bottom:'.(int)$sec['padding_bottom'].'px;';
  return $x;
}
/** Renders a single dynamic block. Used both for the full page and for AJAX add/duplicate fragments. */
function render_block(array $sec,bool $preview,bool $editMode=false): string {
  $id=(int)$sec['id']; $type=$sec['block_type']??'feature'; $layout=$sec['layout']??'image-right';
  if($type==='hero'){
    $active=(int)($sec['is_active']??1);
    $klass='dynamic-block ara-hero block-hero '.e($sec['custom_class']??'');
    if($editMode && !$active) $klass.=' cms-hidden-block';
    $styleAttr=ara_block_style($sec);
    $editAttrs=$editMode?' data-cms-block="'.$id.'" data-block-type="hero" data-block-layout="'.e($layout).'" data-active="'.$active.'"':'';
    $hs=get_settings();
    $heroLayout=$hs['hero_layout']??'stacked';
    $heroData=$hs;
    $heroData['hero_image']=$sec['image']??($hs['hero_image']??'');
    $heroData['digital_image']=$sec['image2']??($hs['digital_image']??'');
    $heroData['contractor_image']=$sec['image3']??($hs['contractor_image']??'');
    $heroData['consulting_image']=$hs['consulting_image']??'';
    $heroData['hero_title']=$sec['title']??($hs['hero_title']??'Solusi Digital untuk Bisnis Anda');
    $heroData['hero_accent']=$sec['subtitle']??($hs['hero_accent']??'With us');
    $heroData['hero_text']=trim(strip_tags((string)($sec['body']??''))) ?: ($hs['hero_text']??'Konsultan, Kontraktor, dan Pengadaan Barang/Jasa');
    $heroData['hero_button']=$sec['button_text']??($hs['hero_button']??'');
    $heroData['hero_button_url']=$sec['button_url']??($hs['hero_button_url']??'#contact');
    $heroBadge=trim((string)($hs['hero_badge']??''));
    $heroSideText=$hs['hero_side_text']??'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.';
    $heroSideCta=$hs['hero_side_cta']??'Join Now!';
    ob_start();
    $domId=trim((string)($sec['anchor_id']??'')); if($domId==='') $domId='block-'.$id; echo '<section id="'.e($domId).'" class="'.$klass.'" data-block-id="'.$id.'" data-anchor-id="'.e($domId).'"'.$editAttrs.' style="'.$styleAttr.'">';
    if($active){
      echo '<div class="hero-inner"><div class="hero-heading">';
      echo '<h1'.ck($editMode,"section.$id.title").'>'.e((string)$heroData['hero_title']).'</h1>';
      if((string)$heroData['hero_accent']!=='' || $editMode) echo '<p class="hero-accent"'.ck($editMode,"section.$id.subtitle").'>'.e((string)$heroData['hero_accent']).'</p>';
      echo '<div class="hero-text"'.ck($editMode,"section.$id.body",true).'>'.($sec['body']??'<p></p>').'</div>';
      if((string)$heroData['hero_button']!=='' || $editMode) echo '<a class="hero-btn" href="'.e((string)$heroData['hero_button_url']).'"'.ck($editMode,"section.$id.button_text").($editMode?' data-cms-href-key="section.'.$id.'.button_url"':'').'>'.e((string)$heroData['hero_button']).'</a>';
      echo '</div>'.ara_hero_visual($heroLayout,$heroData,$preview,$editMode);
      $vpBadge=trim((string)$heroBadge);
      echo '<aside class="hero-side">';
      if($vpBadge!=='') echo '<div class="hero-badge"'.($editMode?' data-cms-key="hero_badge"':'').'>'.e($vpBadge).'</div>';
      if(trim($heroSideText)!=='') echo '<div class="hero-side-copy"'.($editMode?' data-cms-key="hero_side_text"':'').'>'.e($heroSideText).'</div>';
      if(trim($heroSideCta)!=='') echo '<a class="hero-side-cta" href="'.e((string)$heroData['hero_button_url']).'"'.($editMode?' data-cms-key="hero_side_cta" data-cms-href-key="section.'.$id.'.button_url"':'').'>'.e($heroSideCta).'</a>';
      echo '<div class="hero-side-socials" aria-label="Social media" style="width:100%;height:30px;min-height:30px;display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap;overflow:visible;">';
      $heroSocials=[
        ['facebook','<svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width:14px;height:14px;max-width:14px;max-height:14px;display:block;flex:none;"><path d="M14 8h3V4h-3c-3.3 0-5 2-5 5v3H6v4h3v4h4v-4h3l1-4h-4V9c0-.7.3-1 1-1z"/></svg>','Facebook'],
        ['instagram','<svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width:14px;height:14px;max-width:14px;max-height:14px;display:block;flex:none;"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.8" r="1.1"/></svg>','Instagram'],
        ['x','<svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width:14px;height:14px;max-width:14px;max-height:14px;display:block;flex:none;"><path d="M5 4h4.2l3.2 4.4L16.2 4H19l-5.4 6.2L19.5 20h-4.2l-3.8-5.1L7.6 20H4.8l5.8-6.8L5 4z"/></svg>','X / Twitter'],
        ['linkedin','<svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width:14px;height:14px;max-width:14px;max-height:14px;display:block;flex:none;"><path d="M5 8H2v12h3V8zm.2-4A1.8 1.8 0 1 0 1.6 4a1.8 1.8 0 0 0 3.6 0zM22 13.2c0-3.6-1.9-5.5-4.8-5.5-2.2 0-3.1 1.2-3.7 2v-1.7h-3v12h3v-6.2c0-1.6.3-3.1 2.2-3.1 1.8 0 1.9 1.7 1.9 3.2V20h3v-6.8z"/></svg>','LinkedIn'],
        ['whatsapp','<svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width:14px;height:14px;max-width:14px;max-height:14px;display:block;flex:none;"><path d="M20 11.7A8 8 0 0 1 8.3 19L4 20l1.1-4A8 8 0 1 1 20 11.7zM9.2 8.1c-.2-.4-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.4-.2.2-.8.8-.8 2s.8 2.4 1 2.6c.1.2 1.6 2.6 4 3.5 2 .8 2.4.6 2.8.6.4-.1 1.4-.6 1.6-1.1.2-.5.2-1 .1-1.1-.1-.1-.3-.2-.7-.4l-1.3-.6c-.3-.1-.5-.2-.7.2l-.5.7c-.1.2-.3.2-.5.1-.3-.1-1.1-.4-1.8-1.1-.7-.6-1.1-1.4-1.2-1.6-.1-.2 0-.3.1-.5l.4-.5c.2-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.6-1.4z"/></svg>','WhatsApp']
      ];
      foreach($heroSocials as [$sk,$icon,$label]){
        $su=trim((string)($hs['social_'.$sk]??''));
        if($su==='') continue;
        if($editMode){
          echo '<button type="button" class="hero-social-edit" style="width:30px;height:30px;min-width:30px;max-width:30px;min-height:30px;max-height:30px;padding:0;display:grid;place-items:center;overflow:hidden;" data-cms-social="'.e($sk).'" title="Edit '.e($label).'" aria-label="Edit '.e($label).'">'.$icon.'</button>';
        } else {
          echo '<a href="'.e($su).'" aria-label="'.e($label).'" target="_blank" rel="noopener noreferrer" style="width:30px;height:30px;min-width:30px;max-width:30px;min-height:30px;max-height:30px;padding:0;display:grid;place-items:center;overflow:hidden;">'.$icon.'</a>';
        }
      }
      echo '</div></aside></div>';
    }
    echo '</section>';
    return ob_get_clean();
  }
  $klass='dynamic-block block-'.preg_replace('/[^a-z0-9_-]/i','',$type).' block-'.preg_replace('/[^a-z0-9_-]/i','',$layout).' '.e($sec['custom_class']??''); if($type==='about') $klass.=' ara-about'; if($type==='contact') $klass.=' ara-contact';
  $active=(int)($sec['is_active']??1);
  if($editMode && !$active) $klass.=' cms-hidden-block';
  $styleAttr=ara_block_style($sec);
  $editAttrs=$editMode?' data-cms-block="'.$id.'" data-block-type="'.e($type).'" data-block-layout="'.e($layout).'" data-active="'.$active.'"':'';
  ob_start();
  $domId=trim((string)($sec['anchor_id']??'')); if($domId==='') $domId=$type==='about'?'about':($type==='contact'?'contact':'block-'.$id); echo '<section id="'.e($domId).'" class="ara-feature '.$klass.'" data-block-id="'.$id.'" data-anchor-id="'.e($domId).'"'.$editAttrs.' style="'.$styleAttr.'">';
  $ek=static fn(string $suffix)=>$editMode?'section.'.$id.'.'.$suffix:'';
  if($type==='about'){
    echo '<div class="about-inner"><span class="eyebrow"'.ck($editMode,"section.$id.subtitle").'>'.e($sec['subtitle']??'TENTANG KAMI').'</span>';
    echo '<h2'.ck($editMode,"section.$id.title").'>'.e($sec['title']??'Tentang PT Ara DigiTalent').'</h2>';
    echo '<div class="about-copy rich-content"'.ck($editMode,"section.$id.body",true).'>'.($sec['body']??'').'</div></div>';
  } elseif($type==='contact'){
    echo '<div class="contact-inner"><span class="eyebrow light"'.ck($editMode,"section.$id.subtitle").'>'.e($sec['subtitle']??'HUBUNGI KAMI').'</span>';
    echo '<h2'.ck($editMode,"section.$id.title").'>'.e($sec['title']??'Mari Bangun Sesuatu Yang Hebat').'</h2>';
    echo '<div class="contact-copy"'.ck($editMode,"section.$id.body",true).'>'.($sec['body']??'').'</div>';
    echo '<form action="'.($preview?'#':'/contact.php').'" method="post" onsubmit="return '.($preview?'false':'true').'">';
    echo '<div class="name-grid"><input name="name" placeholder="Nama" required><input name="last_name" placeholder="Nama belakang"></div>';
    echo '<input type="email" name="email" placeholder="Surel Anda*" required><textarea name="message" placeholder="Pesan*" required></textarea>';
    echo '<button type="submit"'.ck($editMode,"section.$id.button_text").'>'.e($sec['button_text']??'Kirim').'</button></form></div>';
  } elseif($type==='spacer'){
    echo '<div style="height:1px"></div>';
  } elseif($type==='gallery'){
    echo '<div class="feature-inner gallery-inner" style="max-width:'.min(1800,max(320,(int)($sec['max_width']??900))).'px"><div class="feature-copy gallery-heading">';
    echo '<span class="eyebrow"'.ck($editMode,"section.$id.subtitle").'>'.e($sec['subtitle']??'').'</span>';
    echo '<h2'.ck($editMode,"section.$id.title").'>'.e($sec['title']??'').'</h2>';
    echo '<div class="rich-content"'.ck($editMode,"section.$id.body",true).'>'.($sec['body']??'').'</div></div>';
    echo '<div class="gallery-grid">';
    echo ara_img((string)($sec['image']??''),'contractor.jpg',$preview,$sec['title']??'',$ek('image'),$editMode);
    echo ara_img((string)($sec['image2']??''),'team.jpg',$preview,$sec['title']??'',$ek('image2'),$editMode);
    echo ara_img((string)($sec['image3']??''),'contractor.jpg',$preview,$sec['title']??'',$ek('image3'),$editMode);
    echo '</div></div>';
  } elseif($type==='quote'){
    echo '<div class="quote-inner" style="max-width:'.min(1800,max(320,(int)($sec['max_width']??900))).'px">';
    echo '<span class="eyebrow"'.ck($editMode,"section.$id.subtitle").'>'.e($sec['subtitle']??'').'</span>';
    echo '<div class="quote-mark">&ldquo;</div>';
    echo '<div class="rich-content quote-content"'.ck($editMode,"section.$id.body",true).'>'.($sec['body']??'').'</div>';
    echo '<h2'.ck($editMode,"section.$id.title").'>'.e($sec['title']??'').'</h2></div>';
  } else {
    echo '<div class="feature-inner" style="max-width:'.min(1800,max(320,(int)($sec['max_width']??900))).'px"><div class="feature-copy">';
    echo '<span class="eyebrow"'.ck($editMode,"section.$id.subtitle").'>'.e($sec['subtitle']??'').'</span>';
    echo '<h2'.ck($editMode,"section.$id.title").'>'.e($sec['title']??'').'</h2>';
    echo '<div class="rich-content"'.ck($editMode,"section.$id.body",true).'>'.($sec['body']??'').'</div>';
    // CTA renders its button below in .cta-button-wrap. Avoid rendering the same button twice.
    if($type!=='cta' && (!empty($sec['button_text']) || $editMode)){
      $hrefAttr=$editMode?' data-cms-href-key="section.'.$id.'.button_url"':'';
      echo '<a class="block-btn" href="'.e($sec['button_url']?:'#contact').'"'.ck($editMode,"section.$id.button_text").$hrefAttr.'>'.e($sec['button_text']??'').'</a>';
    }
    echo '</div>';
    if($type!=='text' && $type!=='cta' && $layout!=='center'){
      echo '<div class="feature-image">'.ara_img((string)($sec['image']??''),'contractor.jpg',$preview,$sec['title']??'',$ek('image'),$editMode).'</div>';
    }
    echo '</div>';
    if($type==='cta'){
      $hrefAttr=$editMode?' data-cms-href-key="section.'.$id.'.button_url"':'';
      echo '<div class="cta-button-wrap"><a class="block-btn" href="'.e($sec['button_url']?:'#contact').'"'.ck($editMode,"section.$id.button_text").$hrefAttr.'>'.e($sec['button_text']?:'Let&rsquo;s Talk').'</a></div>';
    }
  }
  echo '</section>';
  return ob_get_clean();
}

function render_site(array $s,array $sections,bool $preview=false,bool $editMode=false,?array $previewOverride=null): void {
  $val=static fn(string $k,string $f=''):string=>e((string)($s[$k]??$f));
  $raw=static fn(string $k,string $f=''):string=>(string)($s[$k]??$f);
  $GLOBALS['__ara_site_settings']=$s;
  $GLOBALS['__ara_typography']=json_decode((string)($s['typography']??'{}'),true);
  if(!is_array($GLOBALS['__ara_typography'])) $GLOBALS['__ara_typography']=[];
  $GLOBALS['__ara_section_typography']=section_typography_map();
  // Per-section typography wins over the legacy/global JSON map.
  foreach($GLOBALS['__ara_section_typography'] as $k=>$v) $GLOBALS['__ara_typography'][$k]=$v;
  ?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=$val('site_name','PT Ara DigiTalent')?></title><?php if(!empty($s['meta_description'])):?><meta name="description" content="<?=$val('meta_description')?>"><?php endif; ?><?php if(!empty($s['canonical_url'])):?><link rel="canonical" href="<?=e($s['canonical_url'])?>"><?php endif; ?><link rel="stylesheet" href="<?=$preview?'../public/assets/css/site.css':'/assets/css/site.css'?>?v=<?=ara_asset_v(__DIR__.'/../public/assets/css/site.css')?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Bebas+Neue&family=Caveat:wght@600;700&family=Playfair+Display:wght@700;900&family=Poppins:wght@500;700;800&family=Space+Grotesk:wght@500;700&display=swap">
<?php $theme=$raw('site_theme','default'); if($theme && $theme!=='default'): $themeFile='theme-'.rawurlencode($theme).'.css'; ?><link rel="stylesheet" href="<?=$preview?'../public/assets/css/'.$themeFile:'/assets/css/'.$themeFile?>?v=<?=ara_asset_v(__DIR__.'/../public/assets/css/'.$themeFile)?>"><?php endif; ?>
<?php $templateCss=$raw('template_css',''); if($templateCss && preg_match('/^[a-zA-Z0-9_-]+\\.css$/',$templateCss)): ?><link rel="stylesheet" href="<?=$preview?'../public/assets/css/templates/'.rawurlencode($templateCss):'/assets/css/templates/'.rawurlencode($templateCss)?>?v=<?=ara_asset_v(__DIR__.'/../public/assets/css/templates/'.$templateCss)?>"><?php endif; ?>
<?php if($editMode): ?><link rel="stylesheet" href="assets/canvas-editor.css?v=<?=filemtime(__DIR__.'/../admin/assets/canvas-editor.css')?>"><?php endif; ?>
<?php $accent=$raw('accent_color'); if($accent && preg_match('/^#[0-9a-fA-F]{3,6}$/',$accent)): ?><style>:root{--ara-accent:<?=e($accent)?>}</style><?php endif; ?>
</head>
<body class="ara-public<?=$theme && $theme!=='default'?' theme-'.e($theme):''?><?php $heroLayout=$raw('hero_layout','stacked'); ?><?=$heroLayout && $heroLayout!=='stacked'?' hero-layout-'.e($heroLayout):''?><?=$editMode?' ara-edit-mode':''?>"<?php if($editMode): ?> data-ce-contact-email="<?=e($raw('contact_email'))?>" data-ce-logo-width="<?=e($raw('logo_width','52'))?>" data-ce-logo-height="<?=e($raw('logo_height','52'))?>" data-ce-header-background="<?=e($raw('header_background','#1b1b1b'))?>" data-ce-header-height-mode="<?=e($headerMode)?>" data-ce-header-height="<?=e($headerHeight)?>" data-ce-header-padding-y="<?=e($headerPadY)?>" style="--ara-header-scroll:<?=$headerEffective?>px" data-ce-meta-description="<?=e($raw('meta_description'))?>" data-ce-canonical-url="<?=e($raw('canonical_url'))?>" data-ce-nav-menu="<?=e(json_encode(get_nav_menu($s)))?>" data-ce-slider-slides="<?=e(json_encode(get_slider_slides($s),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>" data-ce-slider-autoplay="<?=e($raw('slider_autoplay','1'))?>" data-ce-slider-duration="<?=e($raw('slider_duration','4'))?>" data-ce-slider-transition="<?=e($raw('slider_transition','fade'))?>" data-ce-slider-dots="<?=e($raw('slider_dots','1'))?>" data-ce-sections="<?=e(json_encode(array_map(function($sec){ return ['id'=>(int)$sec['id'],'title'=>(string)($sec['title']??''),'anchor_id'=>(string)($sec['anchor_id']??'')]; }, $sections)))?>" data-ce-site-theme="<?=e($theme)?>" data-ce-hero-layout="<?=e($heroLayout)?>" data-ce-accent-color="<?=e($raw('accent_color'))?>" data-ce-hidden-templates="<?=e($raw('hidden_templates','[]'))?>" data-ce-typography="<?=e(json_encode($GLOBALS['__ara_typography']))?>" data-ce-template-css="<?=e($raw('template_css'))?>" data-ce-template-name="<?=e($raw('template_name'))?>" data-ce-custom-templates="<?=e($raw('template_library','[]'))?>" data-ce-block-registry="<?=e(json_encode(ara_block_registry(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>" data-ce-template-backup="<?=e($raw('template_backup',''))?>" data-ce-last-template-revision="<?=e($raw('last_template_revision','0'))?>" data-ce-social-facebook="<?=e($raw('social_facebook'))?>" data-ce-social-instagram="<?=e($raw('social_instagram'))?>" data-ce-social-x="<?=e($raw('social_x'))?>" data-ce-social-linkedin="<?=e($raw('social_linkedin'))?>" data-ce-social-whatsapp="<?=e($raw('social_whatsapp'))?>"<?php if($previewOverride): ?> data-ce-previewing="1" data-ce-preview-theme="<?=e($previewOverride['theme'])?>" data-ce-preview-layout="<?=e($previewOverride['layout'])?>"<?php endif; ?><?php endif; ?><div class="ara-site">
<?php $brandLogo=trim($raw('logo_image')); $brandLogoWidth=min(240,max(28,(int)($raw('logo_width','52')?:52))); $brandLogoHeight=min(180,max(28,(int)($raw('logo_height','52')?:52))); $headerBg=$raw('header_background','#1b1b1b'); if(!preg_match('/^#[0-9a-fA-F]{3,8}$/',$headerBg)) $headerBg='#1b1b1b'; $headerMode=$raw('header_height_mode','auto')==='custom'?'custom':'auto'; $headerHeight=min(320,max(48,(int)($raw('header_height','70')?:70))); $headerPadY=min(80,max(0,(int)($raw('header_padding_y','12')??12))); $headerContentHeight=max(48,$brandLogoHeight+($headerPadY*2)); $headerEffective=$headerMode==='custom'?max($headerHeight,$headerContentHeight):$headerContentHeight; ?>
<header class="ara-header" data-ce-header="1" style="--ara-logo-width:<?=$brandLogoWidth?>px;--ara-logo-height:<?=$brandLogoHeight?>px;--ara-header-pad-y:<?=$headerPadY?>px;--ara-header-custom-height:<?=$headerHeight?>px;--ara-header-effective-height:<?=$headerEffective?>px;--ara-header-scroll:<?=$headerEffective?>px;--ara-header-min-height:48px;background:<?=e($headerBg)?> !important;min-height:<?=$headerEffective?>px !important;height:<?=$headerEffective?>px !important;padding-top:<?=$headerPadY?>px !important;padding-bottom:<?=$headerPadY?>px !important;overflow:visible !important;"><div class="ara-brand-wrap"><span class="ara-brand-logo-slot<?= $brandLogo?' has-logo':' is-placeholder' ?>"><?php if($brandLogo || $editMode): ?><img class="ara-brand-logo" src="<?= $brandLogo?e(ara_img_url($brandLogo,'', $preview)):'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22 viewBox=%220 0 100 100%22%3E%3Crect x=%221%22 y=%221%22 width=%2298%22 height=%2298%22 rx=%2214%22 fill=%22%23333333%22 stroke=%22%23777777%22 stroke-dasharray=%225 5%22/%3E%3Cpath d=%22M50 30v40M30 50h40%22 stroke=%22%23ffffff%22 stroke-width=%228%22 stroke-linecap=%22round%22/%3E%3C/svg%3E' ?>" alt="Logo" data-cms-image="logo_image"<?= ' style="width:'.$brandLogoWidth.'px;height:'.$brandLogoHeight.'px;max-width:240px;max-height:none;object-fit:contain"' ?>><?php endif; ?></span><?php if($editMode): ?><span class="ce-logo-actions"><span class="ce-logo-resize" title="Seret untuk mengatur lebar & tinggi logo">◢</span><button type="button" class="ce-logo-delete" title="Hapus logo">✕</button></span><?php endif; ?><?php if($editMode): ?><span class="ara-brand"<?=ck(true,'site_name')?>><?=$val('site_name','PT Ara DigiTalent')?></span><?php else: ?><a class="ara-brand" href="#top"<?=ck(false,'site_name')?>><?=$val('site_name','PT Ara DigiTalent')?></a><?php endif; ?></div><nav id="araMainNav"<?=$editMode?' data-ce-nav="1"':''?>><?php $navTypeCss=ara_nav_typography_css(); foreach(get_nav_menu($s) as $item): ?><a href="<?=e($item['url'])?>"<?= $navTypeCss?' style="'.e($navTypeCss).'"':'' ?>><?=e($item['label'])?></a><?php endforeach; ?></nav><button type="button" class="ara-menu-toggle" aria-label="Buka menu" aria-expanded="false" aria-controls="araMainNav"><span></span><span></span><span></span></button><?php if($editMode): ?><button type="button" class="ce-header-edit" id="ceHeaderEdit" title="Edit Header">⚙ Header</button><?php endif; ?></header>
<main id="top">
<?php /* V18: Hero is a normal section. No hardcoded/global Hero renderer. */ ?>
<?php foreach($sections as $sec){ echo render_block($sec,$preview,$editMode); } ?>
</main><footer class="ara-footer"><div class="socials"><?php $socials=[['facebook','f','Facebook'],['instagram','ig','Instagram'],['x','x','X / Twitter'],['linkedin','in','LinkedIn'],['whatsapp','wa','WhatsApp']]; foreach($socials as [$sk,$icon,$label]): $su=trim((string)($s['social_'.$sk]??'')); if($su!==''): ?><a href="<?=e($su)?>" target="_blank" rel="noopener" aria-label="<?=e($label)?>"<?php if($editMode): ?> data-cms-social="<?=e($sk)?>" title="Edit <?=e($label)?>"<?php endif; ?>><?=e($icon)?></a><?php endif; ?><?php endforeach; ?></div><strong<?=ck($editMode,'site_name')?>><?=$val('site_name','PT Ara DigiTalent')?></strong><small<?=ck($editMode,'footer_text')?>><?=$val('footer_text','PT Ara DigiTalent')?></small></footer>
<button type="button" id="araBackToTop" class="ara-back-to-top" aria-label="Kembali ke atas" title="Kembali ke atas">↑</button>
</div>
<?php if($editMode): ?><script>window.ARA_CSRF=<?=json_encode($GLOBALS['__ara_csrf']??'')?>;window.ARA_AJAX_URL='ajax.php';</script><script src="assets/canvas-editor.js?v=<?=filemtime(__DIR__.'/../admin/assets/canvas-editor.js')?>"></script><script src="../public/assets/js/hero-slider.js?v=<?=ara_asset_v(__DIR__.'/../public/assets/js/hero-slider.js')?>" defer></script><script src="../public/assets/js/mobile-nav.js?v=<?=ara_asset_v(__DIR__.'/../public/assets/js/mobile-nav.js')?>" defer></script>
<?php else: ?><script src="<?=$preview?'../public/assets/js/back-to-top.js':'/assets/js/back-to-top.js'?>?v=<?=ara_asset_v(__DIR__.'/../public/assets/js/back-to-top.js')?>" defer></script><script src="<?=$preview?'../public/assets/js/hero-slider.js':'/assets/js/hero-slider.js'?>?v=<?=ara_asset_v(__DIR__.'/../public/assets/js/hero-slider.js')?>" defer></script><script src="<?=$preview?'../public/assets/js/mobile-nav.js':'/assets/js/mobile-nav.js'?>?v=<?=ara_asset_v(__DIR__.'/../public/assets/js/mobile-nav.js')?>" defer></script><?php endif; ?>
</body></html><?php }
