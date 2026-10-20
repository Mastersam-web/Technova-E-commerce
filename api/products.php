<?php
declare(strict_types=1);
define('IS_API', true);
require __DIR__ . '/../includes/bootstrap.php';

require_method('GET');

$rows = db()->query(
    "SELECT p.slug, p.name, c.name AS category, p.price, p.old_price, p.rating,
            p.reviews, p.is_featured, p.image
       FROM products p
       JOIN categories c ON c.id = p.category_id
      WHERE p.is_active = 1
      ORDER BY p.id"
)->fetchAll();

$products = array_map(function (array $r): array {
    $price = (float) $r['price'];
    $old   = $r['old_price'] !== null ? (float) $r['old_price'] : null;
    $disc  = ($old !== null && $old > $price) ? '-' . (int) round((1 - $price / $old) * 100) . '%' : '';
    return [
        'id'         => $r['slug'],
        'name'       => $r['name'],
        'category'   => $r['category'],
        'discount'   => $disc,
        'price'      => $price,
        'oldPrice'   => ($old !== null && $old > $price) ? $old : null,
        'rating'     => (int) $r['rating'],
        'reviews'    => (int) $r['reviews'],
        'isFeatured' => (bool) $r['is_featured'],
        'image'      => $r['image'],
    ];
}, $rows);

header('Cache-Control: public, max-age=60');
json_out(['ok' => true, 'products' => $products]);
