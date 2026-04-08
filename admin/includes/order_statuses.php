<?php

declare(strict_types=1);

function admin_order_status_map(): array
{
    return [
        'pending' => ['id' => 1, 'label' => 'Новый'],
        'processing' => ['id' => 2, 'label' => 'В обработке'],
        'shipped' => ['id' => 3, 'label' => 'Отправлен'],
        'in_transit' => ['id' => 6, 'label' => 'В пути'],
        'completed' => ['id' => 4, 'label' => 'Доставлен'],
        'cancelled' => ['id' => 5, 'label' => 'Отменён'],
    ];
}

function admin_order_status_slug_by_id(int $id): string
{
    foreach (admin_order_status_map() as $slug => $data) {
        if ((int)$data['id'] === $id) {
            return $slug;
        }
    }

    return 'pending';
}
