<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__.'/../app/Security.php';
ara_require_install();
require_once __DIR__.'/../app/Content.php';
require_once __DIR__.'/../app/site-template.php';
try { $s=get_settings(); $sections=get_sections(); } catch(Throwable $e) { $s=[]; $sections=[]; }
render_site($s,$sections,false);
