<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/yookassa.php';

function webhook_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function get_request_ip(): string
{
    return trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
}

function is_ip_allowed(string $ip): bool
{
    $raw = trim((string)(getenv('YOOKASSA_ALLOWED_IPS') ?: ''));
    if ($raw === '') {
        return true;
    }

    $allowed = array_filter(array_map('trim', explode(',', $raw)), static function ($item) {
        return $item !== '';
    });

    return in_array($ip, $allowed, true);
}

function has_valid_webhook_token(): bool
{
    $expected = trim((string)(getenv('YOOKASSA_WEBHOOK_TOKEN') ?: ''));
    if ($expected === '') {
        return true;
    }

    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        return false;
    }

    return hash_equals($expected, trim((string)$matches[1]));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    webhook_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (!has_valid_webhook_token()) {
    webhook_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$remoteIp = get_request_ip();
if (!is_ip_allowed($remoteIp)) {
    webhook_response(['ok' => false, 'error' => 'Forbidden'], 403);
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    webhook_response(['ok' => false, 'error' => 'Empty body'], 400);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    webhook_response(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$event = (string)($payload['event'] ?? '');
if ($event !== 'payment.succeeded') {
    webhook_response(['ok' => true, 'ignored' => true]);
}

$object = $payload['object'] ?? null;
if (!is_array($object)) {
    webhook_response(['ok' => false, 'error' => 'Invalid object'], 400);
}

$paymentId = (string)($object['id'] ?? '');
$paymentStatus = (string)($object['status'] ?? '');
$amountValue = (string)($object['amount']['value'] ?? '');
$metadataOrderId = (int)($object['metadata']['order_id'] ?? 0);

if ($paymentId === '' || $paymentStatus !== 'succeeded' || $amountValue === '' || $metadataOrderId <= 0) {
    webhook_response(['ok' => false, 'error' => 'Invalid payment payload'], 400);
}

try {
    $verifiedPayment = yookassa_api_request('GET', '/payments/' . rawurlencode($paymentId));

    $verifiedStatus = (string)($verifiedPayment['status'] ?? '');
    $verifiedAmount = (string)($verifiedPayment['amount']['value'] ?? '');
    $verifiedOrderId = (int)($verifiedPayment['metadata']['order_id'] ?? 0);

    if ($verifiedStatus !== 'succeeded') {
        webhook_response(['ok' => false, 'error' => 'Payment not succeeded in YooKassa'], 400);
    }

    if ($verifiedOrderId !== $metadataOrderId) {
        webhook_response(['ok' => false, 'error' => 'Metadata mismatch'], 400);
    }

    $pdo->beginTransaction();

    $stmtOrder = $pdo->prepare('SELECT id, total_price, payment_status, payment_id FROM orders WHERE id = ? LIMIT 1 FOR UPDATE');
    $stmtOrder->execute([$metadataOrderId]);
    $order = $stmtOrder->fetch();

    if (!$order) {
        throw new RuntimeException('Order not found.');
    }

    $orderAmount = number_format((float)$order['total_price'], 2, '.', '');
    $eventAmount = number_format((float)$amountValue, 2, '.', '');
    $apiAmount = number_format((float)$verifiedAmount, 2, '.', '');

    if ($orderAmount !== $eventAmount || $orderAmount !== $apiAmount) {
        throw new RuntimeException('Amount mismatch.');
    }

    if ((string)($order['payment_id'] ?? '') !== $paymentId) {
        throw new RuntimeException('Payment ID mismatch.');
    }

    if ((string)$order['payment_status'] === 'paid') {
        $pdo->commit();
        webhook_response(['ok' => true, 'idempotent' => true]);
    }

    if ((string)$order['payment_status'] !== 'pending_payment') {
        throw new RuntimeException('Invalid order payment status transition.');
    }

    $stmtUpdate = $pdo->prepare(
        "UPDATE orders
         SET payment_status = 'paid', paid_at = NOW()
         WHERE id = ? AND payment_status = 'pending_payment'"
    );
    $stmtUpdate->execute([$metadataOrderId]);

    if ($stmtUpdate->rowCount() !== 1) {
        throw new RuntimeException('Order was not updated.');
    }

    $pdo->commit();
    webhook_response(['ok' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('YooKassa webhook error: ' . $e->getMessage());
    webhook_response(['ok' => false, 'error' => 'Webhook processing failed'], 500);
}
