<?php
require_once __DIR__ . '/../app/Security.php';
require_once __DIR__ . '/../app/Content.php';
require_once __DIR__ . '/../app/Mailer.php';
admin_required();

$pdo = Database::pdo();
$smtp = smtp_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        foreach (['host','port','username','encryption','from_email','from_name'] as $k) {
            $st = $pdo->prepare(
                'INSERT INTO smtp_settings(key,value) VALUES(?,?)
                 ON CONFLICT(key) DO UPDATE SET value=excluded.value'
            );
            $st->execute([$k, trim($_POST[$k] ?? '')]);
        }

        // Jangan menghapus password lama jika field password dikosongkan.
        $password = $_POST['password'] ?? '';
        if ($password !== '') {
            $st = $pdo->prepare(
                'INSERT INTO smtp_settings(key,value) VALUES(?,?)
                 ON CONFLICT(key) DO UPDATE SET value=excluded.value'
            );
            $st->execute(['password', $password]);
        }

        flash('SMTP tersimpan.');
    } elseif ($action === 'test') {
        $to = trim($_POST['test_to'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('✕ Email tujuan tidak valid.');
        } else {
            [$ok, $msg] = send_smtp(
                $to,
                'ARA CMS SMTP Test',
                '<h2>SMTP Test berhasil</h2><p>Konfigurasi SMTP CMS berjalan.</p>'
            );
            flash(($ok ? '✓ ' : '✕ ') . $msg);
        }
    }

    header('Location: smtp.php');
    exit;
}
?>
<?php require_once __DIR__ . '/_header.php'; ?>

<h1>SMTP</h1>

<form method="post">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <input type="hidden" name="action" value="save">

    <?php foreach (['host','port','username','from_email','from_name'] as $k): ?>
        <label>
            <?=e(ucwords(str_replace('_', ' ', $k)))?>
            <input
                name="<?=e($k)?>"
                value="<?=e($smtp[$k] ?? '')?>"
                <?= $k === 'from_email' ? 'type="email"' : ($k === 'port' ? 'type="number"' : 'type="text"') ?>
            >
        </label>
    <?php endforeach; ?>

    <label>
        Password
        <input
            type="password"
            name="password"
            value=""
            placeholder="Kosongkan untuk mempertahankan password lama"
            autocomplete="new-password"
        >
    </label>

    <label>
        Encryption
        <select name="encryption">
            <option value="tls" <?=($smtp['encryption'] ?? 'tls') === 'tls' ? 'selected' : ''?>>STARTTLS</option>
            <option value="ssl" <?=($smtp['encryption'] ?? '') === 'ssl' ? 'selected' : ''?>>SSL</option>
            <option value="none" <?=($smtp['encryption'] ?? '') === 'none' ? 'selected' : ''?>>None</option>
        </select>
    </label>

    <button type="submit">Save SMTP</button>
</form>

<hr>

<h2>Test Email</h2>

<form method="post">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <input type="hidden" name="action" value="test">

    <label>
        Send to
        <input type="email" name="test_to" required>
    </label>

    <button type="submit">Send Test</button>
</form>

<?php require_once __DIR__ . '/_footer.php'; ?>
