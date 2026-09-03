<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Security.php';
require_once __DIR__.'/../app/Content.php';
require_once __DIR__.'/../app/site-template.php';
admin_required();

$ALLOWED_THEMES=['default','minimal','bold'];
$ALLOWED_LAYOUTS=['stacked','split','overlay','grid','slider'];
$theme=in_array($_GET['theme']??'', $ALLOWED_THEMES, true) ? $_GET['theme'] : 'default';
$layout=in_array($_GET['layout']??'', $ALLOWED_LAYOUTS, true) ? $_GET['layout'] : 'stacked';

$s=get_settings();
$s['site_theme']=$theme;
$s['hero_layout']=$layout;
$sections=get_sections();
$firstBlock=null;
foreach($sections as $sec){ if(($sec['block_type']??'')!=='spacer'){ $firstBlock=$sec; break; } }

$val=static fn(string $k,string $f=''):string=>e((string)($s[$k]??$f));
$raw=static fn(string $k,string $f=''):string=>(string)($s[$k]??$f);
?><!doctype html><html lang="id"><head><meta charset="utf-8">
<link rel="stylesheet" href="../public/assets/css/site.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Bebas+Neue&family=Caveat:wght@600;700&family=Playfair+Display:wght@700;900&family=Poppins:wght@500;700;800&family=Space+Grotesk:wght@500;700&display=swap">
<?php if($theme!=='default'): ?><link rel="stylesheet" href="../public/assets/css/theme-<?=rawurlencode($theme)?>.css"><?php endif; ?>
<style>html,body{cursor:default}.ara-header{position:relative!important}.ara-contact,.ara-about,.ara-footer{display:none}</style>
</head>
<body class="ara-public theme-<?=e($theme)?><?=$layout!=='stacked'?' hero-layout-'.e($layout):''?>">
<div class="ara-site">
<header class="ara-header"><a class="ara-brand" href="#"><?=$val('site_name','PT Ara DigiTalent')?></a><nav><?php foreach(get_nav_menu($s) as $item): ?><a href="#"><?=e($item['label'])?></a><?php endforeach; ?></nav></header>
<main>
<section class="ara-hero"><div class="hero-inner"><div class="hero-heading"><h1><?=$val('hero_title','Solusi Digital untuk Bisnis Anda')?></h1><?php if($raw('hero_accent')!==''):?><p class="hero-accent"><?=$val('hero_accent')?></p><?php endif;?><p><?=$val('hero_text','Konsultan, Kontraktor, dan Pengadaan Barang/Jasa')?></p><?php if($raw('hero_button')!==''):?><a class="hero-btn" href="#"><?=$val('hero_button')?></a><?php endif;?><?php if($raw('hero_badge')!==''):?><div class="hero-badge"><?=$val('hero_badge')?></div><?php endif;?></div><?=ara_hero_visual($layout,$s,true,false)?></div></section>
<?php if($firstBlock): echo render_block($firstBlock,true,false); endif; ?>
</main>
</div>
</body></html>
