<?php

declare(strict_types=1);

function admin_order_status_map(): array
{
    static $map = [
        'pending'    => ['id' => 1, 'label' => 'Новый'],
        'processing' => ['id' => 2, 'label' => 'В обработке'],
        'shipped'    => ['id' => 3, 'label' => 'Отправлен'],
        'in_transit' => ['id' => 6, 'label' => 'В пути'],
        'completed'  => ['id' => 4, 'label' => 'Доставлен'],
        'cancelled'  => ['id' => 5, 'label' => 'Отменён'],
    ];

    return $map;
}

function admin_order_get_status_data(int $id): array
{
    foreach (admin_order_status_map() as $slug => $data) {
        if ($data['id'] === $id) {
            return array_merge(['slug' => $slug], $data);
        }
    }

    return ['slug' => 'pending', 'id' => 1, 'label' => 'Новый'];
}

function admin_order_status_slug_by_id(int $id): string
{
    return admin_order_get_status_data($id)['slug'];
}

function admin_order_status_id_by_slug(string $slug): int
{
    $map = admin_order_status_map();
    return isset($map[$slug]) ? (int)$map[$slug]['id'] : 1;
}

function admin_order_status_label_by_id(int $id): string
{
    return admin_order_get_status_data($id)['label'];
}
