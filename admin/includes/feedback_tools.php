<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/feedback.php';

function admin_feedback_status_labels(): array
{
    return [
        'new' => 'Новый',
        'in_progress' => 'В работе',
        'done' => 'Обработан',
    ];
}

function admin_feedback_schema(PDO $pdo): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    app_feedback_ensure_schema($pdo);

    $cache = [
        'has_phone' => false,
        'has_status' => false,
        'has_status_updated_at' => false,
    ];

    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM feedback');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $columns = [];
        foreach ($rows as $columnRow) {
            $field = (string)($columnRow['Field'] ?? '');
            if ($field !== '') {
                $columns[] = $field;
            }
        }
        
        if ($columns) {
            $cache['has_phone'] = in_array('phone', $columns, true);
            $cache['has_status'] = in_array('status', $columns, true);
            $cache['has_status_updated_at'] = in_array('status_updated_at', $columns, true);
        }
    } catch (Throwable $e) {
        admin_log_error('feedback_schema', $e);
    }

    return $cache;
}

function admin_feedback_get_fields(array $schema): array
{
    $fields = ['id', 'name', 'email', 'subject', 'message', 'created_at'];
    
    if ($schema['has_phone']) $fields[] = 'phone';
    if ($schema['has_status']) $fields[] = 'status';
    if ($schema['has_status_updated_at']) $fields[] = 'status_updated_at';

    return $fields;
}

function admin_feedback_fetch_list(PDO $pdo): array
{
    $schema = admin_feedback_schema($pdo);
    $fields = admin_feedback_get_fields($schema);

    $sql = 'SELECT ' . implode(', ', $fields) . ' FROM feedback ORDER BY created_at DESC, id DESC';
    
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        admin_log_error('feedback_fetch_list', $e);
        return [];
    }
}

function admin_feedback_fetch_one(PDO $pdo, int $id): ?array
{
    $schema = admin_feedback_schema($pdo);
    $fields = admin_feedback_get_fields($schema);

    $sql = 'SELECT ' . implode(', ', $fields) . ' FROM feedback WHERE id = ? LIMIT 1';
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        admin_log_error('feedback_fetch_one', $e);
        return null;
    }
}

function admin_feedback_update_status(PDO $pdo, int $id, string $status): bool
{
    $schema = admin_feedback_schema($pdo);
    if (!$schema['has_status']) {
        return false;
    }

    $labels = admin_feedback_status_labels();
    if (!isset($labels[$status])) {
        return false;
    }

    try {
        if ($schema['has_status_updated_at']) {
            $stmt = $pdo->prepare('UPDATE feedback SET status = ?, status_updated_at = NOW() WHERE id = ? LIMIT 1');
        } else {
            $stmt = $pdo->prepare('UPDATE feedback SET status = ? WHERE id = ? LIMIT 1');
        }

        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        admin_log_error('feedback_update_status', $e);
        return false;
    }
}