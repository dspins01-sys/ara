<?php
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
function start_secure_session(): void { if (session_status() !== PHP_SESSION_ACTIVE) { session_name(SESSION_NAME); session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Lax','cookie_secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']); } }
function csrf_token(): string { start_secure_session(); if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void { start_secure_session(); if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Invalid CSRF token'); } }
function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function admin_required(): void { start_secure_session(); if (empty($_SESSION['admin_id'])) { header('Location: /admin/login.php'); exit; } }
function flash(?string $msg=null): ?string { start_secure_session(); if ($msg!==null) $_SESSION['flash']=$msg; $x=$_SESSION['flash']??null; unset($_SESSION['flash']); return $x; }
