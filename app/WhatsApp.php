<?php
declare(strict_types=1);

function whatsapp_settings(): array {
    return [
        'enabled' => setting('wa_enabled', '0') === '1',
        'url' => trim(setting('wa_service_url', 'http://127.0.0.1:3000')),
        'api_key' => trim(setting('wa_api_key', '')),
        'account_id' => trim(setting('wa_account_id', '')),
        'admin_phone' => trim(setting('wa_admin_phone', '')),
        'template' => setting('wa_message_template', "🔔 NEW CONTACT\n\nNama: {name}\nEmail: {email}\n\nPesan:\n{message}\n\n🌐 {site_name}"),
    ];
}

function whatsapp_normalize_url(string $url): string {
    $url = rtrim(trim($url), '/');
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        throw new RuntimeException('WA Service URL harus diawali http:// atau https://.');
    }
    return $url;
}

function whatsapp_send(string $to, string $message, ?array $settings = null): array {
    $settings ??= whatsapp_settings();
    $url = whatsapp_normalize_url($settings['url']) . '/api/v1/messages';
    $apiKey = (string)$settings['api_key'];
    $accountId = (string)$settings['account_id'];
    $to = trim($to);
    $message = trim($message);

    if ($apiKey === '') throw new RuntimeException('WA API Key belum dikonfigurasi.');
    if ($accountId === '') throw new RuntimeException('WA Account ID belum dikonfigurasi.');
    if ($to === '') throw new RuntimeException('Nomor WhatsApp admin belum dikonfigurasi.');
    if ($message === '') throw new RuntimeException('Pesan WhatsApp kosong.');

    $payload = json_encode([
        'accountId' => $accountId,
        'to' => $to,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) throw new RuntimeException('Gagal membuat payload WhatsApp.');

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$apiKey}\r\n",
            'content' => $payload,
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    $status = 0;
    foreach (($http_response_header ?? []) as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) { $status = (int)$m[1]; break; }
    }
    $data = is_string($response) ? json_decode($response, true) : null;
    if ($status < 200 || $status >= 300 || !is_array($data) || ($data['success'] ?? false) !== true) {
        $detail = is_array($data) ? (string)($data['error'] ?? 'WA Service rejected the request.') : 'WA Service tidak dapat dihubungi.';
        throw new RuntimeException("WA Service HTTP {$status}: {$detail}");
    }
    return $data;
}

function whatsapp_render_template(string $template, string $name, string $email, string $message, string $siteName): string {
    return strtr($template, [
        '{name}' => $name,
        '{email}' => $email,
        '{message}' => $message,
        '{site_name}' => $siteName,
    ]);
}
