<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
// Builder must always render the current DB state; never let browser/proxy cache stale HTML.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__.'/../app/Security.php';
require_once __DIR__.'/../app/Content.php';
require_once __DIR__.'/../app/site-template.php';
admin_required();
$s=get_settings();
$sections=get_all_sections();
$GLOBALS['__ara_csrf']=csrf_token();

$ALLOWED_THEMES=['default','minimal','bold'];
$ALLOWED_LAYOUTS=['stacked','split','overlay','grid','slider'];
$previewOverride=null;
if(isset($_GET['preview_theme']) || isset($_GET['preview_layout'])){
  $pt=in_array($_GET['preview_theme']??'', $ALLOWED_THEMES, true) ? $_GET['preview_theme'] : ($s['site_theme']??'default');
  $pl=in_array($_GET['preview_layout']??'', $ALLOWED_LAYOUTS, true) ? $_GET['preview_layout'] : ($s['hero_layout']??'stacked');
  $previewOverride=['theme'=>$pt,'layout'=>$pl];
  $s['site_theme']=$pt;
  $s['hero_layout']=$pl;
}

render_site($s,$sections,true,true,$previewOverride);
