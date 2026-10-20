<?php
declare(strict_types=1);

function config(string $key, $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/config.php';
    }
    $val = $cfg;
    foreach (explode('.', $key) as $part) {
        if (!is_array($val) || !array_key_exists($part, $val)) {
            return $default;
        }
        $val = $val[$part];
    }
    return $val;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
            config('db.host'), config('db.name'), config('db.charset', 'utf8mb4'));
        $pdo = new PDO($dsn, (string) config('db.user'), (string) config('db.pass'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/** Nettoie une chaîne : trim, supprime caractères de contrôle, tronque. */
function clean($v, int $max = 255): string
{
    $v = is_string($v) ? $v : '';
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr(trim($v), 0, $max, 'UTF-8');
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        json_out(['ok' => false, 'message' => 'Méthode non autorisée.'], 405);
    }
    if ($method !== 'GET') {
        // Protection CSRF légère : l'Origin doit correspondre à l'hôte du site.
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '') {
            $oh = parse_url($origin, PHP_URL_HOST);
            $sh = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
            if (!$oh || strcasecmp($oh, $sh) !== 0) {
                json_out(['ok' => false, 'message' => 'Origine non autorisée.'], 403);
            }
        }
    }
}

function read_input(): array
{
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input', false, null, 0, 65536);
        $data = json_decode($raw ?: '', true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

/** Anti-spam : retourne false si l'IP a dépassé $max appels sur $window secondes. */
function rate_limit(string $bucket, int $max, int $window): bool
{
    $ip = client_ip();
    $pdo = db();
    if (random_int(1, 50) === 1) {
        $pdo->exec("DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)");
    }
    $st = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE bucket=? AND ip=? AND created_at > (NOW() - INTERVAL $window SECOND)");
    $st->execute([$bucket, $ip]);
    if ((int) $st->fetchColumn() >= $max) {
        return false;
    }
    $pdo->prepare("INSERT INTO rate_limits (bucket, ip) VALUES (?, ?)")->execute([$bucket, $ip]);
    return true;
}

function send_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool
{
    if (!config('mail.enabled', false)) {
        return false;
    }
    $strip = fn(string $s) => trim(str_replace(["\r", "\n", "%0a", "%0d"], ' ', $s));
    $from  = $strip((string) config('mail.from'));
    $fname = $strip((string) config('mail.from_name'));
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: =?UTF-8?B?' . base64_encode($fname) . "?= <$from>",
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $strip($replyTo);
    }
    $subj = '=?UTF-8?B?' . base64_encode($strip($subject)) . '?=';
    return (bool) @mail($strip($to), $subj, $body, implode("\r\n", $headers));
}

function fmt_price($n): string
{
    return number_format((float) $n, 2, ',', ' ') . ' DA';
}

/** Hash factice : égalise le temps de réponse quand l'e-mail n'existe pas. */
const DUMMY_HASH = '$2y$10$rCvCi2QQdfvFwpRM5cCGI.SEgNrT5xXI94R0D1ANtgc283aPRu9Qm';

function customer_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('technova_client');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_start();
    }
}

function unread_notifications(int $customerId): int
{
    try {
        $st = db()->prepare("SELECT COUNT(*) FROM notifications WHERE customer_id = ? AND is_read = 0");
        $st->execute([$customerId]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0; // table absente (migration non faite) : ne casse pas le site
    }
}

/** Crée une notification pour un client (ne lève jamais d'exception). */
function notify(int $customerId, string $type, string $title, string $message = '', ?string $link = null): void
{
    try {
        db()->prepare("INSERT INTO notifications (customer_id, type, title, message, link) VALUES (?,?,?,?,?)")
            ->execute([$customerId, $type, mb_substr($title, 0, 150), mb_substr($message, 0, 500), $link]);
    } catch (Throwable $e) {
        error_log('[TechNova] notify: ' . $e->getMessage());
    }
}

const ORDER_STATUSES = [
    'pending'   => 'En attente',
    'confirmed' => 'Confirmée',
    'shipped'   => 'Expédiée',
    'delivered' => 'Livrée',
    'cancelled' => 'Annulée',
];

/** Notification + e-mail envoyés au client quand le statut de sa commande change. */
function notify_order_status(int $customerId, string $reference, string $status, string $email = '', string $name = ''): void
{
    $texts = [
        'confirmed' => ['Commande confirmée', "Votre commande $reference a été confirmée. Nous la préparons."],
        'shipped'   => ['Commande expédiée', "Votre commande $reference est en route vers vous."],
        'delivered' => ['Commande livrée', "Votre commande $reference a été livrée. Merci de votre confiance !"],
        'cancelled' => ['Commande annulée', "Votre commande $reference a été annulée."],
        'pending'   => ['Commande en attente', "Votre commande $reference est de nouveau en attente de traitement."],
    ];
    if (!isset($texts[$status])) {
        return;
    }
    [$title, $msg] = $texts[$status];
    notify($customerId, 'order_' . $status, $title, $msg, 'orders.php?ref=' . $reference);
    if ($email !== '') {
        send_mail($email, "[TechNova] $title - $reference", "Bonjour $name,\n\n$msg\n\nSuivez vos commandes dans votre espace client : "
            . rtrim((string) config('site.url'), '/') . "/compte/\n");
    }
}

function public_customer(array $c): array
{
    return [
        'unread'  => unread_notifications((int) $c['id']),
        'id'      => (int) $c['id'],
        'name'    => $c['name'],
        'email'   => $c['email'],
        'phone'   => $c['phone'],
        'address' => $c['address'],
        'city'    => $c['city'],
    ];
}

/** Client connecté (ou null). */
function current_customer(): ?array
{
    customer_session();
    if (empty($_SESSION['customer_id'])) {
        return null;
    }
    $st = db()->prepare("SELECT id, name, email, phone, address, city FROM customers WHERE id = ? AND is_active = 1");
    $st->execute([(int) $_SESSION['customer_id']]);
    $c = $st->fetch();
    if (!$c) {
        unset($_SESSION['customer_id']);
        return null;
    }
    return $c;
}

/** Exige un corps JSON : bloque les soumissions de formulaires inter-sites (CSRF). */
function require_json(): void
{
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
        json_out(['ok' => false, 'message' => 'Requête invalide.'], 415);
    }
}
