<?php
require_once __DIR__.'/../app/Security.php';
require_once __DIR__.'/../app/Content.php';
require_once __DIR__.'/../app/Mailer.php';
admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim((string)($_POST['contact_email'] ?? ''));
    $autoEnabled = isset($_POST['contact_autoreply_enabled']) ? '1' : '0';
    $autoSubject = trim((string)($_POST['contact_autoreply_subject'] ?? ''));
    $autoBody = trim((string)($_POST['contact_autoreply_body'] ?? ''));

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('✕ Email penerima tidak valid.');
    } elseif ($autoEnabled === '1' && $autoSubject === '') {
        flash('✕ Subject auto-reply wajib diisi jika auto-reply aktif.');
    } elseif ($autoEnabled === '1' && $autoBody === '') {
        flash('✕ Isi auto-reply wajib diisi jika auto-reply aktif.');
    } else {
        save_setting('contact_email', $email);
        save_setting('contact_autoreply_enabled', $autoEnabled);
        save_setting('contact_autoreply_subject', $autoSubject);
        save_setting('contact_autoreply_body', $autoBody);
        flash('✓ Pengaturan Contact Form tersimpan.');
    }
    header('Location: contact.php');
    exit;
}

require_once __DIR__.'/_header.php';

$contactEmail = setting('contact_email', '');
$smtp = smtp_settings();
$fallback = trim((string)($smtp['from_email'] ?? ''));
$autoEnabled = setting('contact_autoreply_enabled', '0') === '1';
$autoSubject = setting('contact_autoreply_subject', 'Terima kasih, pesan Anda sudah kami terima');
$autoBody = setting('contact_autoreply_body', 'Halo {name},<br><br>Terima kasih sudah menghubungi {site_name}. Pesan Anda sudah kami terima dan tim kami akan segera menghubungi Anda.<br><br>Salam,<br>{site_name}');
?>

<h1>Contact Form</h1>
<p>Atur email penerima pesan website dan balasan otomatis untuk pengunjung.</p>

<form method="post">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">

    <div class="card" style="padding:18px">
        <h3 style="margin-top:0">Pesan Masuk</h3>
        <label>
            Email Penerima Contact Form
            <input type="email" name="contact_email" value="<?=e($contactEmail)?>" placeholder="admin@domain.com">
        </label>
        <p class="smtp-help">Pesan pengunjung akan dikirim ke email ini melalui SMTP yang sudah dikonfigurasi.</p>
        <p class="smtp-help">Jika dikosongkan, tujuan otomatis memakai From Email SMTP: <b><?=e($fallback ?: 'belum diatur')?></b></p>
    </div>

    <div class="card" style="margin-top:20px;padding:18px">
        <h3 style="margin-top:0">Auto-Reply Pengunjung</h3>
        <label style="display:flex;gap:10px;align-items:center;margin:0 0 16px">
            <input type="checkbox" name="contact_autoreply_enabled" value="1" <?=$autoEnabled?'checked':''?>>
            <span>Aktifkan auto-reply setelah pengunjung mengirim pesan</span>
        </label>

        <label>
            Subject Auto-Reply
            <input type="text" name="contact_autoreply_subject" value="<?=e($autoSubject)?>" placeholder="Terima kasih, pesan Anda sudah kami terima">
        </label>

        <label style="margin-top:14px">
            Isi Auto-Reply
            <textarea name="contact_autoreply_body" rows="10" placeholder="Tulis pesan balasan otomatis..."><?=e($autoBody)?></textarea>
        </label>

        <div class="smtp-help" style="margin-top:10px">
            <b>Placeholder yang tersedia:</b><br>
            <code>{name}</code> nama pengunjung ·
            <code>{email}</code> email pengunjung ·
            <code>{message}</code> isi pesan ·
            <code>{site_name}</code> nama website
            <br><br>HTML sederhana seperti <code>&lt;br&gt;</code>, <code>&lt;b&gt;</code> dan <code>&lt;p&gt;</code> boleh digunakan.
        </div>
    </div>

    <button class="btn primary" type="submit" style="margin-top:18px">Simpan Pengaturan</button>
</form>

<?php require_once __DIR__.'/_footer.php'; ?>
