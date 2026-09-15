<?php
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
function start_secure_session(): void { if (session_status() !== PHP_SESSION_ACTIVE) { session_name(SESSION_NAME); session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Lax','cookie_secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']); } }
function csrf_token(): string { start_secure_session(); if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void { start_secure_session(); if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Invalid CSRF token'); } }
function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
/** V20.9.4: normalize user-controlled href/src values and reject executable schemes. */
function ara_safe_url(?string $url, string $fallback=''): string {
  $url=trim((string)($url ?? ''));
  if($url==='') return $fallback;
  // Strip ASCII control characters from the value we will emit.
  $url=preg_replace('/[\x00-\x1F\x7F]/','',$url) ?? '';
  if($url==='') return $fallback;
  // Browsers can normalize whitespace/control variants before interpreting a scheme.
  $probe=preg_replace('/[\x00-\x20\x7F]+/','',$url) ?? $url;
  if(preg_match('/^(?:javascript|vbscript|data|file|blob):/i',$probe)) return $fallback;
  if(str_starts_with($probe,'//')) return $fallback;
  if(preg_match('/^([a-z][a-z0-9+.-]*):/i',$probe,$m)){
    $scheme=strtolower($m[1]);
    if(!in_array($scheme,['http','https','mailto','tel'],true)) return $fallback;
  }
  return $url;
}
function ara_app_base_path(): string {
  $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
  foreach (['/public/', '/admin/'] as $marker) {
    $pos = strpos($script, $marker);
    if ($pos !== false) return rtrim(substr($script, 0, $pos), '/');
  }
  $dir = str_replace('\\', '/', dirname($script));
  return $dir === '/' || $dir === '.' ? '' : rtrim($dir, '/');
}
function ara_is_installed(): bool {
  if (!is_file(DB_PATH)) return false;
  try {
    $pdo = Database::pdo();
    $stmt = $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='sections' LIMIT 1");
    return (bool)$stmt->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}
function ara_require_install(): void {
  if (ara_is_installed()) return;
  start_secure_session();
  $_SESSION['install_notice'] = 'CMS belum di-install. Silakan jalankan install.php terlebih dahulu.';
  header('Location: ' . ara_app_base_path() . '/install.php');
  exit;
}
function admin_required(): void { start_secure_session(); if (!ara_is_installed()) { $_SESSION['install_notice'] = 'CMS belum di-install. Silakan jalankan install.php terlebih dahulu.'; header('Location: ' . ara_app_base_path() . '/install.php'); exit; } if (empty($_SESSION['admin_id'])) { header('Location: ' . ara_app_base_path() . '/admin/login.php'); exit; } }
function flash(?string $msg=null): ?string {
  start_secure_session();
  // Setter: simpan pesan untuk request berikutnya. Jangan langsung mengambilnya,
  // karena itu membuat flash hilang sebelum redirect selesai.
  if ($msg !== null) {
    $_SESSION['flash'] = $msg;
    return null;
  }
  // Getter: ambil sekali lalu hapus agar pesan tidak muncul lagi setelah refresh.
  $x = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  return $x;
}
