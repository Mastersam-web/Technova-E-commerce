<?php
declare(strict_types=1);
define('IS_API', true);
require __DIR__ . '/../includes/bootstrap.php';

/** Commandes (avec articles) du client connecté. */
function customer_orders(int $id): array
{
    $pdo = db();
    $st = $pdo->prepare("SELECT id, reference, total, status, created_at, address, city FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 50");
    $st->execute([$id]);
    $orders = $st->fetchAll();
    if (!$orders) {
        return [];
    }
    $ids = array_column($orders, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $it = $pdo->prepare("SELECT order_id, product_name, unit_price, quantity FROM order_items WHERE order_id IN ($in) ORDER BY id");
    $it->execute($ids);
    $items = [];
    foreach ($it->fetchAll() as $r) {
        $items[$r['order_id']][] = [
            'name'      => $r['product_name'],
            'unitPrice' => (float) $r['unit_price'],
            'quantity'  => (int) $r['quantity'],
        ];
    }
    return array_map(fn($o) => [
        'reference' => $o['reference'],
        'total'     => (float) $o['total'],
        'status'    => $o['status'],
        'createdAt' => $o['created_at'], // UTC
        'address'   => trim($o['address'] . ' ' . $o['city']),
        'items'     => $items[$o['id']] ?? [],
    ], $orders);
}

function valid_phone(string $p): bool
{
    return $p === '' || (bool) preg_match('/^[0-9+()\s.-]{6,30}$/', $p);
}

// ---- GET : état de la session (client + commandes)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $c = current_customer();
    json_out([
        'ok'       => true,
        'customer' => $c ? public_customer($c) : null,
        'orders'   => $c ? customer_orders((int) $c['id']) : [],
    ]);
}

require_method('POST');
require_json();
$in = read_input();
$action = (string) ($in['action'] ?? '');
$pdo = db();

switch ($action) {

    case 'register': {
        $name  = clean($in['name'] ?? '', 120);
        $email = strtolower(clean($in['email'] ?? '', 190));
        $pass  = (string) ($in['password'] ?? '');
        $phone = clean($in['phone'] ?? '', 30);
        if (mb_strlen($name) < 2)                       json_out(['ok' => false, 'message' => 'Nom invalide.'], 422);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['ok' => false, 'message' => 'Adresse e-mail invalide.'], 422);
        if (strlen($pass) < 8 || strlen($pass) > 200)   json_out(['ok' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.'], 422);
        if (!valid_phone($phone))                       json_out(['ok' => false, 'message' => 'Numéro de téléphone invalide.'], 422);
        if (!rate_limit('register', (int) config('limits.register_per_10min', 5), 600)) {
            json_out(['ok' => false, 'message' => 'Trop de tentatives. Réessayez dans quelques minutes.'], 429);
        }
        $st = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $st->execute([$email]);
        if ($st->fetch()) {
            json_out(['ok' => false, 'message' => 'Un compte existe déjà avec cette adresse e-mail. Connectez-vous.'], 409);
        }
        $pdo->prepare("INSERT INTO customers (name, email, password_hash, phone, last_login) VALUES (?,?,?,?,NOW())")
            ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $phone]);
        $id = (int) $pdo->lastInsertId();
        customer_session();
        session_regenerate_id(true);
        $_SESSION['customer_id'] = $id;
        send_mail($email, 'Bienvenue chez ' . config('site.name', 'TechNova'),
            "Bonjour $name,\n\nVotre compte " . config('site.name', 'TechNova') . " a bien été créé.\nVous pouvez maintenant commander et suivre vos commandes depuis « Mon compte ».\n");
        notify($id, 'welcome', 'Bienvenue chez ' . config('site.name', 'TechNova') . ' !', 'Votre compte est créé. Complétez votre profil pour commander plus vite.', 'profile.php');
        $c = current_customer();
        json_out(['ok' => true, 'message' => "Bienvenue, $name ! Votre compte a été créé.", 'customer' => public_customer($c), 'orders' => []]);
    }

    case 'login': {
        $email = strtolower(clean($in['email'] ?? '', 190));
        $pass  = (string) ($in['password'] ?? '');
        if (!rate_limit('customer_login', (int) config('limits.customer_login_per_15min', 10), 900)) {
            json_out(['ok' => false, 'message' => 'Trop de tentatives. Réessayez dans 15 minutes.'], 429);
        }
        $st = $pdo->prepare("SELECT id, name, password_hash, is_active FROM customers WHERE email = ?");
        $st->execute([$email]);
        $row = $st->fetch();
        $ok = password_verify($pass, $row['password_hash'] ?? DUMMY_HASH) && $row && (int) $row['is_active'] === 1;
        if (!$ok) {
            json_out(['ok' => false, 'message' => 'E-mail ou mot de passe incorrect.'], 401);
        }
        customer_session();
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int) $row['id'];
        $pdo->prepare("UPDATE customers SET last_login = NOW() WHERE id = ?")->execute([$row['id']]);
        $c = current_customer();
        json_out(['ok' => true, 'message' => 'Bienvenue, ' . $c['name'] . ' !', 'customer' => public_customer($c), 'orders' => customer_orders((int) $c['id'])]);
    }

    case 'logout': {
        customer_session();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        json_out(['ok' => true, 'message' => 'Vous êtes déconnecté(e).']);
    }

    case 'update': {
        $c = current_customer();
        if (!$c) json_out(['ok' => false, 'code' => 'auth_required', 'message' => 'Veuillez vous connecter.'], 401);
        $name    = clean($in['name'] ?? '', 120);
        $phone   = clean($in['phone'] ?? '', 30);
        $address = clean($in['address'] ?? '', 255);
        $city    = clean($in['city'] ?? '', 100);
        if (mb_strlen($name) < 2)  json_out(['ok' => false, 'message' => 'Nom invalide.'], 422);
        if (!valid_phone($phone))  json_out(['ok' => false, 'message' => 'Numéro de téléphone invalide.'], 422);
        $newPass = (string) ($in['newPassword'] ?? '');
        if ($newPass !== '') {
            if (strlen($newPass) < 8 || strlen($newPass) > 200) json_out(['ok' => false, 'message' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.'], 422);
            $st = $pdo->prepare("SELECT password_hash FROM customers WHERE id = ?");
            $st->execute([$c['id']]);
            if (!password_verify((string) ($in['currentPassword'] ?? ''), (string) $st->fetchColumn())) {
                json_out(['ok' => false, 'message' => 'Mot de passe actuel incorrect.'], 403);
            }
            $pdo->prepare("UPDATE customers SET password_hash = ? WHERE id = ?")->execute([password_hash($newPass, PASSWORD_DEFAULT), $c['id']]);
            notify((int) $c['id'], 'security', 'Mot de passe modifié', 'Votre mot de passe vient d\'être changé. Si ce n\'est pas vous, réinitialisez-le immédiatement.', 'security.php');
        }
        $pdo->prepare("UPDATE customers SET name=?, phone=?, address=?, city=? WHERE id=?")
            ->execute([$name, $phone, $address, $city, $c['id']]);
        $c = current_customer();
        json_out(['ok' => true, 'message' => 'Profil mis à jour.', 'customer' => public_customer($c)]);
    }

    case 'forgot': {
        $email = strtolower(clean($in['email'] ?? '', 190));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['ok' => false, 'message' => 'Adresse e-mail invalide.'], 422);
        if (!rate_limit('forgot', (int) config('limits.forgot_per_hour', 5), 3600)) {
            json_out(['ok' => false, 'message' => 'Trop de demandes. Réessayez plus tard.'], 429);
        }
        $st = $pdo->prepare("SELECT id FROM customers WHERE email = ? AND is_active = 1");
        $st->execute([$email]);
        if ($id = $st->fetchColumn()) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("UPDATE customers SET reset_token_hash = ?, reset_expires = (UTC_TIMESTAMP() + INTERVAL 1 HOUR) WHERE id = ?")
                ->execute([hash('sha256', $token), $id]);
            $site = rtrim((string) config('site.url'), '/');
            send_mail($email, 'Réinitialisation de votre mot de passe',
                "Vous avez demandé la réinitialisation de votre mot de passe.\n\nCliquez sur ce lien (valable 1 heure) :\n$site/reset.php?t=$token\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.\n");
        }
        // Réponse identique que le compte existe ou non (pas d'énumération).
        json_out(['ok' => true, 'message' => 'Si un compte existe avec cette adresse, un e-mail de réinitialisation vient d\'être envoyé.']);
    }

    default:
        json_out(['ok' => false, 'message' => 'Action inconnue.'], 400);
}
