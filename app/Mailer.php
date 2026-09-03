<?php
declare(strict_types=1);
require_once __DIR__ . '/Database.php';

function smtp_settings(): array
{
    return Database::pdo()
        ->query("SELECT key, value FROM smtp_settings")
        ->fetchAll(PDO::FETCH_KEY_PAIR);
}

function send_smtp(string $to, string $subject, string $html, string $replyTo = ''): array
{
    $s = smtp_settings();

    foreach (['host','port','username','password','encryption','from_email','from_name'] as $k) {
        $s[$k] = $s[$k] ?? '';
    }

    if (!$s['host'] || !$s['port'] || !$s['from_email']) {
        return [false, 'SMTP belum dikonfigurasi.'];
    }

    $port = (int) $s['port'];
    $remote = ($s['encryption'] === 'ssl' ? 'ssl://' : '') . $s['host'] . ':' . $port;

    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false
        ]
    ]);

    $fp = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        15,
        STREAM_CLIENT_CONNECT,
        $ctx
    );

    if (!$fp) {
        return [false, "SMTP connect gagal: $errstr"];
    }

    $read = function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 515)) !== false) {
            $out .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        return $out;
    };

    $write = function (string $cmd) use ($fp, $read): string {
        fwrite($fp, $cmd . "\r\n");
        return $read();
    };

    $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $write('EHLO ' . $serverName);

    if ($s['encryption'] === 'tls') {
        $r = $write('STARTTLS');

        if (substr($r, 0, 3) !== '220') {
            fclose($fp);
            return [false, 'STARTTLS ditolak.'];
        }

        $crypto = stream_socket_enable_crypto(
            $fp,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        );

        if ($crypto !== true) {
            fclose($fp);
            return [false, 'TLS handshake gagal.'];
        }

        $write('EHLO ' . $serverName);
    }

    if ($s['username'] !== '') {
        $r = $write('AUTH LOGIN');

        if (substr($r, 0, 3) !== '334') {
            fclose($fp);
            return [false, 'SMTP AUTH LOGIN ditolak.'];
        }

        $r = $write(base64_encode($s['username']));

        if (substr($r, 0, 3) !== '334') {
            fclose($fp);
            return [false, 'SMTP username ditolak.'];
        }

        $r = $write(base64_encode($s['password']));

        if (substr($r, 0, 3) !== '235') {
            fclose($fp);
            return [false, 'SMTP authentication gagal.'];
        }
    }

    $r = $write('MAIL FROM:<' . $s['from_email'] . '>');
    if (substr($r, 0, 3) !== '250') {
        fclose($fp);
        return [false, 'MAIL FROM gagal.'];
    }

    $r = $write('RCPT TO:<' . $to . '>');
    if (substr($r, 0, 3) !== '250' && substr($r, 0, 3) !== '251') {
        fclose($fp);
        return [false, 'RCPT TO gagal.'];
    }

    $r = $write('DATA');
    if (substr($r, 0, 3) !== '354') {
        fclose($fp);
        return [false, 'SMTP DATA ditolak.'];
    }

    $encodeHeader = static function (string $value): string {
        return function_exists('mb_encode_mimeheader')
            ? mb_encode_mimeheader($value, 'UTF-8')
            : $value;
    };

    $headers =
        'From: ' . $encodeHeader($s['from_name']) . ' <' . $s['from_email'] . ">\r\n" .
        'To: <' . $to . ">\r\n" .
        'Subject: ' . $encodeHeader($subject) . "\r\n" .
        "MIME-Version: 1.0\r\n" .
        "Content-Type: text/html; charset=UTF-8\r\n";

    if ($replyTo) {
        $headers .= 'Reply-To: <' . $replyTo . ">\r\n";
    }

    fwrite($fp, $headers . "\r\n" . $html . "\r\n.\r\n");

    $r = $read();
    $write('QUIT');
    fclose($fp);

    if (substr($r, 0, 3) === '250') {
        return [true, 'Email berhasil dikirim.'];
    }

    return [false, 'SMTP DATA gagal: ' . trim($r)];
}
