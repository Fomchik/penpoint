<?php

declare(strict_types=1);

if (!defined('YOOKASSA_API_URL')) {
    define('YOOKASSA_API_URL', 'https://api.yookassa.ru/v3');
}

//  В продакшене ключи нужно хранить в .env

function yookassa_get_config(): array
{
    $shopId = trim((string)(getenv('YOOKASSA_SHOP_ID') ?: ''));
    $secretKey = trim((string)(getenv('YOOKASSA_SECRET_KEY') ?: ''));

    if ($shopId === '' || $secretKey === '') {
        throw new RuntimeException('YooKassa credentials are not configured.');
    }

    return [
        'shop_id' => $shopId,
        'secret_key' => $secretKey,
        'api_url' => YOOKASSA_API_URL,
    ];
}

function yookassa_ssl_verify_enabled(): bool
{
    $raw = strtolower(trim((string)(getenv('YOOKASSA_SSL_VERIFY') ?: '1')));
    return !in_array($raw, ['0', 'false', 'off', 'no'], true);
}

function yookassa_verify_webhook_signature(string $rawBody): bool
{
    $secret = trim((string)(getenv('YOOKASSA_WEBHOOK_SIGNATURE_SECRET') ?: ''));
    if ($secret === '') {
        return true;
    }

    $header = trim((string)($_SERVER['HTTP_SIGNATURE'] ?? ''));
    if ($header === '') {
        return false;
    }

    $expectedHex = hash_hmac('sha256', $rawBody, $secret);
    $expectedBase64 = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

    $provided = $header;
    if (stripos($provided, 'sha256=') === 0) {
        $provided = trim(substr($provided, 7));
    }

    return hash_equals($expectedHex, $provided) || hash_equals($expectedBase64, $provided);
}

function yookassa_api_request(string $method, string $path, ?array $payload = null, ?string $idempotenceKey = null): array
{
    $config = yookassa_get_config();
    $url = rtrim($config['api_url'], '/') . '/' . ltrim($path, '/');

    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL extension is required for YooKassa integration.');
    }

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Unable to initialize cURL.');
    }

    $headers = ['Content-Type: application/json'];
    if ($idempotenceKey !== null && $idempotenceKey !== '') {
        $headers[] = 'Idempotence-Key: ' . $idempotenceKey;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $config['shop_id'] . ':' . $config['secret_key'],
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => yookassa_ssl_verify_enabled(),
        CURLOPT_SSL_VERIFYHOST => yookassa_ssl_verify_enabled() ? 2 : 0,
    ]);

    if ($payload !== null) {
        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonBody === false) {
            throw new RuntimeException('Unable to encode YooKassa payload to JSON.');
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
    }

    $responseBody = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($responseBody === false) {
        throw new RuntimeException('YooKassa request failed: ' . $curlError);
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid YooKassa JSON response.');
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $description = (string)($decoded['description'] ?? 'Unexpected YooKassa API error');
        throw new RuntimeException($description);
    }

    return $decoded;
}
