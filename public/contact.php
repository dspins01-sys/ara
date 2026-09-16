<?php
declare(strict_types=1);

// Public contact endpoint: load the same application helpers used by the live site.
require_once __DIR__.'/../app/Security.php';
require_once __DIR__.'/../app/Content.php';
require_once __DIR__.'/../app/Mailer.php';
require_once __DIR__.'/../app/WhatsApp.php';

ara_require_install();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ara_app_base_path() . '/#contact');
    exit;
}

$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$message) {
    http_response_code(422);
    exit('Data tidak valid.');
}

// Contact recipient is explicitly configured in Contact Form settings.
$to = trim(setting('contact_email', ''));
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    error_log('ARA contact submission rejected: Contact Form recipient is not configured or invalid.');
    start_secure_session();
    $_SESSION['contact_flash'] = '✕ Contact Form belum dikonfigurasi. Email penerima wajib diatur oleh administrator.';
    header('Location: ' . ara_app_base_path() . '/?contact=sent#contact');
    exit;
}

$st = Database::pdo()->prepare('INSERT INTO messages(name,email,message) VALUES(?,?,?)');
$st->execute([$name, $email, $message]);

$smtp = smtp_settings();
$fromEmail = trim((string)($smtp['from_email'] ?? ''));
$siteName = setting('site_name', 'ARA CMS');

// Notify site owner. The message is already stored in Messages, so a mail failure
// must never make the visitor lose the submission.
if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
    [$sent, $sendMessage] = send_smtp(
        $to,
        'New website inquiry from ' . $name,
        '<h2>New Inquiry</h2>' .
        '<p><b>Name:</b> ' . e($name) . '</p>' .
        '<p><b>Email:</b> ' . e($email) . '</p>' .
        '<p><b>Message:</b><br>' . nl2br(e($message)) . '</p>',
        $email
    );
    if (!$sent) {
        error_log('ARA contact notification failed: ' . $sendMessage);
    }
}

// Optional WhatsApp notification. WhatsApp is a separate service accessed only via HTTP API.
$wa = whatsapp_settings();
if ($wa['enabled']) {
    try {
        $waMessage = whatsapp_render_template($wa['template'], $name, $email, $message, $siteName);
        whatsapp_send($wa['admin_phone'], $waMessage, $wa);
    } catch (Throwable $waError) {
        // The contact is already stored and email handling is independent from WhatsApp.
        error_log('ARA WhatsApp notification failed: ' . $waError->getMessage());
    }
}

// Optional custom auto-reply to the visitor. It uses the same SMTP account.
if (setting('contact_autoreply_enabled', '0') === '1' && $fromEmail && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    $replySubject = setting('contact_autoreply_subject', 'Terima kasih, pesan Anda sudah kami terima');
    $replyBody = setting('contact_autoreply_body', 'Halo {name},<br><br>Terima kasih sudah menghubungi {site_name}. Pesan Anda sudah kami terima dan tim kami akan segera menghubungi Anda.<br><br>Salam,<br>{site_name}');
    $replace = [
        '{name}' => e($name),
        '{email}' => e($email),
        '{message}' => nl2br(e($message)),
        '{site_name}' => e($siteName),
    ];
    $replySubject = strtr($replySubject, $replace);
    $replyBody = strtr($replyBody, $replace);

    [$replySent, $replyMessage] = send_smtp(
        $email,
        $replySubject,
        $replyBody,
        $fromEmail
    );
    if (!$replySent) {
        error_log('ARA contact auto-reply failed: ' . $replyMessage);
    }
}

start_secure_session();
$_SESSION['contact_flash'] = '✓ Pesan berhasil terkirim. Terima kasih, kami akan segera menghubungi Anda.';
header('Location: ' . ara_app_base_path() . '/?contact=sent#contact');
exit;
