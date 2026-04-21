<?php

declare(strict_types=1);

function product_option_catalog(): array
{
    return [
        'color' => 'Цвет',
        'size' => 'Размер',
        'format' => 'Формат',
        'volume' => 'Объем',
        'thickness' => 'Толщина',
        'set_quantity' => 'Количество в наборе',
        'sheet_quantity' => 'Количество листов',
        'paper_density' => 'Плотность бумаги',
        'paper_type' => 'Тип бумаги',
        'binding_type' => 'Тип крепления',
        'cover_type' => 'Тип обложки',
        'hardness' => 'Жесткость',
    ];
}

function product_option_code_from_name(string $name): string
{
    $catalog = product_option_catalog();
    $lookup = array_flip($catalog);
    $trimmed = trim($name);
    if (isset($lookup[$trimmed])) {
        return (string)$lookup[$trimmed];
    }

    $code = mb_strtolower($trimmed, 'UTF-8');
    $map = [
        'а' => 'a',
        'б' => 'b',
        'в' => 'v',
        'г' => 'g',
        'д' => 'd',
        'е' => 'e',
        'ё' => 'e',
        'ж' => 'zh',
        'з' => 'z',
        'и' => 'i',
        'й' => 'i',
        'к' => 'k',
        'л' => 'l',
        'м' => 'm',
        'н' => 'n',
        'о' => 'o',
        'п' => 'p',
        'р' => 'r',
        'с' => 's',
        'т' => 't',
        'у' => 'u',
        'ф' => 'f',
        'х' => 'h',
        'ц' => 'c',
        'ч' => 'ch',
        'ш' => 'sh',
        'щ' => 'sch',
        'ъ' => '',
        'ы' => 'y',
        'ь' => '',
        'э' => 'e',
        'ю' => 'yu',
        'я' => 'ya',
    ];
    $code = strtr($code, $map);
    $code = preg_replace('/[^a-z0-9]+/', '_', $code) ?: '';
    $code = trim($code, '_');

    return $code !== '' ? $code : 'param';
}
