<?php
declare(strict_types=1);
define('IS_API', true);
require __DIR__ . '/../includes/bootstrap.php';

require_method('POST');
$in = read_input();

if (!empty($in['website'])) { // honeypot
    json_out(['ok' => true, 'message' => 'Inscription validée avec succès !']);
}

$email = strtolower(clean($in['email'] ?? '', 190));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['ok' => false, 'message' => 'Adresse e-mail invalide.'], 422);
}

if (!rate_limit('newsletter', (int) config('limits.newsletter_per_10min', 5), 600)) {
    json_out(['ok' => false, 'message' => 'Trop de tentatives. Réessayez dans quelques minutes.'], 429);
}

$pdo = db();
$st = $pdo->prepare("SELECT id, is_active, token FROM newsletter_subscribers WHERE email = ?");
$st->execute([$email]);
$row = $st->fetch();

if ($row && (int) $row['is_active'] === 1) {
    json_out(['ok' => true, 'already' => true, 'message' => 'Vous êtes déjà inscrit(e) à la newsletter.']);
}

if ($row) { // réinscription
    $token = $row['token'];
    $pdo->prepare("UPDATE newsletter_subscribers SET is_active=1, unsubscribed_at=NULL, ip=? WHERE id=?")
        ->execute([client_ip(), $row['id']]);
} else {
    $token = bin2hex(random_bytes(20));
    $pdo->prepare("INSERT INTO newsletter_subscribers (email, token, ip) VALUES (?,?,?)")
        ->execute([$email, $token, client_ip()]);
}

$site = rtrim((string) config('site.url'), '/');
send_mail(
    $email,
    'Bienvenue chez ' . config('site.name', 'TechNova'),
    "Merci pour votre inscription à la newsletter " . config('site.name', 'TechNova') . " !\n\n"
    . "Vous recevrez nos nouveautés et offres spéciales.\n\n"
    . "Pour vous désinscrire à tout moment : $site/unsubscribe.php?t=$token\n"
);

json_out(['ok' => true, 'message' => 'Inscription validée avec succès !']);
