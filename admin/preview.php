<?php
require_once __DIR__.'/../app/Content.php';
require_once __DIR__.'/../app/Security.php';
require_once __DIR__.'/../app/site-template.php';
try { $s=get_settings(); $sections=get_sections(); } catch(Throwable $e) { $s=[
  'site_name'=>'Ara Digitalent','hero_title'=>'Transform Your Business With Digital Excellence','hero_text'=>'Solusi digital, teknologi, dan talent untuk membantu bisnis tumbuh lebih cepat.','hero_button'=>'Mulai Konsultasi','about_title'=>'Partner Digital Untuk Pertumbuhan Bisnis','about_text'=>'Kami membantu bisnis membangun fondasi digital yang kuat melalui strategi, teknologi, dan talenta terbaik.','contact_title'=>'Mari Bangun Sesuatu Yang Hebat','contact_text'=>'Ceritakan kebutuhan bisnis Anda dan tim kami akan menghubungi Anda.','footer_text'=>'© 2026 Ara Digitalent. All rights reserved.'
]; $sections=[]; }
render_site($s,$sections,true);
