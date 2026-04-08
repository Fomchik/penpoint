<?php
require_once __DIR__ . '/../includes/security.php';
app_start_session();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/yookassa.php';
require_once __DIR__ . '/../includes/payments.php';
require_once __DIR__ . '/../includes/cart.php';

$orderId = (int)($_GET['order_id'] ?? 0);
$status = 'failed';

if ($orderId > 0) {
    try {
        app_ensure_order_payment_schema($pdo);
        $stmtOrder = $pdo->prepare('SELECT id, payment_status, payment_id FROM orders WHERE id = ? LIMIT 1');
        $stmtOrder->execute([$orderId]);
        $order = $stmtOrder->fetch();

        if ($order) {
            if ((string)$order['payment_status'] === 'paid') {
                $status = 'paid';
            } elseif ((string)$order['payment_status'] === 'pending_payment' && (string)($order['payment_id'] ?? '') !== '') {
                $payment = yookassa_api_request('GET', '/payments/' . rawurlencode((string)$order['payment_id']));
                $paymentStatus = (string)($payment['status'] ?? '');

                if ($paymentStatus === 'succeeded') {
                    $stmtUpdate = $pdo->prepare("UPDATE orders SET payment_status = 'paid', paid_at = NOW(), status_id = 2 WHERE id = ? AND payment_status <> 'paid'");
                    $stmtUpdate->execute([$orderId]);
                    cart_clear();
                    $status = 'paid';
                } elseif ($paymentStatus === 'pending' || $paymentStatus === 'waiting_for_capture') {
                    $status = 'pending';
                } else {
                    $stmtFail = $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ? AND payment_status = 'pending_payment'");
                    $stmtFail->execute([$orderId]);
                    $status = 'failed';
                }
            }
        }
    } catch (Throwable $e) {
        error_log('payment_return error: ' . $e->getMessage());
    }
}

header('Location: /pages/checkout.php?payment_return=1&order_id=' . $orderId . '&payment_status=' . urlencode($status));
exit;
