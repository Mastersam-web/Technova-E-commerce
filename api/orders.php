<?php
declare(strict_types=1);
define('IS_API', true);
require __DIR__ . '/../includes/bootstrap.php';

require_method('POST');
require_json();

// Commande réservée aux clients connectés.
$customer = current_customer();
if (!$customer) {
    json_out(['ok' => false, 'code' => 'auth_required', 'message' => 'Veuillez vous connecter ou créer un compte pour commander.'], 401);
}
$in = read_input();

$c = is_array($in['customer'] ?? null) ? $in['customer'] : [];
$name    = clean($c['name'] ?? '', 120);
$phone   = clean($c['phone'] ?? '', 30);
$address = clean($c['address'] ?? '', 255);
$city    = clean($c['city'] ?? '', 100);
$email   = $customer['email']; // l'e-mail du compte fait foi

if (mb_strlen($name) < 2)                          json_out(['ok' => false, 'message' => 'Nom invalide.'], 422);
if (!preg_match('/^[0-9+()\s.-]{6,30}$/', $phone)) json_out(['ok' => false, 'message' => 'Numéro de téléphone invalide.'], 422);
if (mb_strlen($address) < 5)                       json_out(['ok' => false, 'message' => 'Adresse de livraison invalide.'], 422);

// Panier : on ne fait jamais confiance au prix envoyé par le navigateur.
$items = is_array($in['items'] ?? null) ? $in['items'] : [];
$wanted = [];
foreach ($items as $it) {
    $slug = clean($it['id'] ?? '', 100);
    $qty  = (int) ($it['quantity'] ?? 0);
    if ($slug === '' || $qty < 1 || $qty > 20) {
        json_out(['ok' => false, 'message' => 'Panier invalide.'], 422);
    }
    $wanted[$slug] = ($wanted[$slug] ?? 0) + $qty;
}
if (!$wanted || count($wanted) > 50) {
    json_out(['ok' => false, 'message' => 'Votre panier est vide !'], 422);
}

if (!rate_limit('order', (int) config('limits.order_per_10min', 5), 600)) {
    json_out(['ok' => false, 'message' => 'Trop de commandes. Réessayez dans quelques minutes.'], 429);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $in_ph = implode(',', array_fill(0, count($wanted), '?'));
    $st = $pdo->prepare("SELECT id, slug, name, price, stock FROM products WHERE is_active=1 AND slug IN ($in_ph) FOR UPDATE");
    $st->execute(array_keys($wanted));
    $found = [];
    foreach ($st->fetchAll() as $p) {
        $found[$p['slug']] = $p;
    }

    $total = 0.0;
    foreach ($wanted as $slug => $qty) {
        if (!isset($found[$slug])) {
            throw new RuntimeException('Un produit de votre panier n\'est plus disponible.');
        }
        if ((int) $found[$slug]['stock'] < $qty) {
            throw new RuntimeException('Stock insuffisant pour « ' . $found[$slug]['name'] . ' ».');
        }
        $total += (float) $found[$slug]['price'] * $qty;
    }

    $ref = 'TN-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $pdo->prepare("INSERT INTO orders (reference, customer_id, customer_name, customer_email, phone, address, city, total, ip)
                   VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$ref, $customer['id'], $name, $email, $phone, $address, $city, $total, client_ip()]);
    $orderId = (int) $pdo->lastInsertId();

    $ins = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity) VALUES (?,?,?,?,?)");
    $dec = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
    $lines = [];
    foreach ($wanted as $slug => $qty) {
        $p = $found[$slug];
        $ins->execute([$orderId, $p['id'], $p['name'], $p['price'], $qty]);
        $dec->execute([$qty, $p['id']]);
        $lines[] = sprintf('- %s x%d = %s', $p['name'], $qty, fmt_price((float) $p['price'] * $qty));
    }
    // Mémorise les coordonnées de livraison dans le profil si elles étaient vides.
    $pdo->prepare("UPDATE customers SET phone = IF(phone='', ?, phone), address = IF(address='', ?, address), city = IF(city='', ?, city) WHERE id = ?")
        ->execute([$phone, $address, $city, $customer['id']]);
    $pdo->commit();
} catch (RuntimeException $e) {
    $pdo->rollBack();
    json_out(['ok' => false, 'message' => $e->getMessage()], 409);
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

$summary = "Commande $ref\n\nClient : $name\nTéléphone : $phone\nAdresse : $address $city\n"
         . ($email !== '' ? "E-mail : $email\n" : '') . "\n" . implode("\n", $lines)
         . "\n\nTotal : " . fmt_price($total) . "\n";
notify((int) $customer['id'], 'order_pending', 'Commande reçue', "Votre commande $ref (" . fmt_price($total) . ") a bien été enregistrée. Nous vous contacterons pour la livraison.", 'orders.php?ref=' . $ref);
send_mail((string) config('mail.to'), "[TechNova] Nouvelle commande $ref", $summary, $email);
send_mail($email, "Confirmation de votre commande $ref",
    "Bonjour $name,\n\nMerci pour votre commande ! Nous vous contacterons rapidement pour la livraison.\n\n$summary");

json_out([
    'ok' => true,
    'reference' => $ref,
    'total' => $total,
    'message' => "Commande validée avec succès ! Réf. $ref. Merci de votre confiance.",
]);
