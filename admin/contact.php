<?php
require_once __DIR__.'/../app/Security.php';
require_once __DIR__.'/../app/Content.php';
require_once __DIR__.'/../app/Mailer.php';
require_once __DIR__.'/../app/WhatsApp.php';
admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string)($_POST['action'] ?? 'save');
    if ($action === 'test_whatsapp') {
        $wa = whatsapp_settings();
        try {
            $testMessage = "🔔 ARA CMS WhatsApp Test\n\nKoneksi API berhasil.\nAccount: {$wa['account_id']}\nWaktu: " . date('Y-m-d H:i:s');
            whatsapp_send($wa['admin_phone'], $testMessage, $wa);
            flash('✓ Test WhatsApp berhasil dikirim.');
        } catch (Throwable $e) {
            flash('✕ Test WhatsApp gagal: ' . $e->getMessage());
        }
        header('Location: contact.php');
        exit;
    }

    $email = trim((string)($_POST['contact_email'] ?? ''));
    $autoEnabled = isset($_POST['contact_autoreply_enabled']) ? '1' : '0';
    $autoSubject = trim((string)($_POST['contact_autoreply_subject'] ?? ''));
    $autoBody = trim((string)($_POST['contact_autoreply_body'] ?? ''));

    if ($email === '') {
        flash('✕ Email Penerima Contact Form wajib diisi.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('✕ Email penerima tidak valid.');
    } elseif ($autoEnabled === '1' && $autoSubject === '') {
        flash('✕ Subject auto-reply wajib diisi jika auto-reply aktif.');
    } elseif ($autoEnabled === '1' && $autoBody === '') {
        flash('✕ Isi auto-reply wajib diisi jika auto-reply aktif.');
    } else {
        $waEnabled = isset($_POST['wa_enabled']) ? '1' : '0';
        $waUrl = trim((string)($_POST['wa_service_url'] ?? ''));
        $waApiKey = trim((string)($_POST['wa_api_key'] ?? ''));
        $waAccount = trim((string)($_POST['wa_account_id'] ?? ''));
        $waPhone = trim((string)($_POST['wa_admin_phone'] ?? ''));
        $waTemplate = trim((string)($_POST['wa_message_template'] ?? ''));
        if ($waEnabled === '1' && ($waUrl === '' || !preg_match('#^https?://#i', $waUrl))) {
            flash('✕ WA Service URL wajib valid jika notifikasi WhatsApp aktif.');
        } elseif ($waEnabled === '1' && $waApiKey === '') {
            flash('✕ WA API Key wajib diisi jika notifikasi WhatsApp aktif.');
        } elseif ($waEnabled === '1' && $waAccount === '') {
            flash('✕ WA Account ID wajib diisi jika notifikasi WhatsApp aktif.');
        } elseif ($waEnabled === '1' && $waPhone === '') {
            flash('✕ Nomor WhatsApp admin wajib diisi jika notifikasi WhatsApp aktif.');
        } elseif ($waEnabled === '1' && $waTemplate === '') {
            flash('✕ Template WhatsApp wajib diisi jika notifikasi WhatsApp aktif.');
        } else {
            save_setting('contact_email', $email);
            save_setting('contact_fallback_email', '');
            save_setting('contact_autoreply_enabled', $autoEnabled);
            save_setting('contact_autoreply_subject', $autoSubject);
            save_setting('contact_autoreply_body', $autoBody);
            save_setting('wa_enabled', $waEnabled);
            save_setting('wa_service_url', $waUrl);
            save_setting('wa_api_key', $waApiKey);
            save_setting('wa_account_id', $waAccount);
            save_setting('wa_admin_phone', $waPhone);
            save_setting('wa_message_template', $waTemplate);
            flash('✓ Pengaturan Contact Form tersimpan.');
        }
    }
    header('Location: contact.php');
    exit;
}

require_once __DIR__.'/_header.php';

$contactEmail = setting('contact_email', '');
$autoEnabled = setting('contact_autoreply_enabled', '0') === '1';
$autoSubject = setting('contact_autoreply_subject', 'Terima kasih, pesan Anda sudah kami terima');
$autoBody = setting('contact_autoreply_body', 'Halo {name},<br><br>Terima kasih sudah menghubungi {site_name}. Pesan Anda sudah kami terima dan tim kami akan segera menghubungi Anda.<br><br>Salam,<br>{site_name}');
$wa = whatsapp_settings();
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
        <p class="smtp-help">Email ini <b>wajib diisi</b> dan menjadi satu-satunya tujuan notifikasi Contact Form.</p>
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

    <div class="card" style="margin-top:20px;padding:18px">
        <h3 style="margin-top:0">WhatsApp Notification</h3>
        <p class="smtp-help">Opsional. ARA CMS tetap berdiri sendiri dan hanya memanggil WA Service melalui HTTP API.</p>
        <label style="display:flex;gap:10px;align-items:center;margin:0 0 16px">
            <input type="checkbox" name="wa_enabled" value="1" <?=$wa['enabled']?'checked':''?>>
            <span>Aktifkan notifikasi WhatsApp ke admin</span>
        </label>
        <label>WA Service URL<input type="url" name="wa_service_url" value="<?=e($wa['url'])?>" placeholder="http://127.0.0.1:3000"></label>
        <label>WA API Key<input type="password" name="wa_api_key" value="<?=e($wa['api_key'])?>" placeholder="WA_API_KEY" autocomplete="new-password"></label>
        <label>WA Account ID<input type="text" name="wa_account_id" value="<?=e($wa['account_id'])?>" placeholder="admin"></label>
        <label>Nomor WhatsApp Admin<input type="text" name="wa_admin_phone" value="<?=e($wa['admin_phone'])?>" placeholder="628123456789"></label>
        <label>Template Pesan<textarea name="wa_message_template" rows="9"><?=e($wa['template'])?></textarea></label>
        <div class="smtp-help"><b>Placeholder:</b> <code>{name}</code> · <code>{email}</code> · <code>{message}</code> · <code>{site_name}</code></div>
    </div>

    <button class="btn primary" type="submit" name="action" value="save" style="margin-top:18px">Simpan Pengaturan</button>
</form>

<form method="post" style="margin-top:10px">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <button class="btn" type="submit" name="action" value="test_whatsapp">Test WhatsApp</button>
</form>

<?php require_once __DIR__.'/_footer.php'; ?>
