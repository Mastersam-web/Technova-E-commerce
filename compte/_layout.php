<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

/** Exige un client connecté, sinon renvoie vers la fenêtre de connexion du site. */
function require_customer(): array
{
    $c = current_customer();
    if (!$c) {
        header('Location: ../index.html#connexion');
        exit;
    }
    return $c;
}

function csrf_token(): string
{
    customer_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    customer_session();
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
    if (!$ok) {
        http_response_code(419);
        exit('Session expirée ou requête invalide. Rechargez la page.');
    }
}

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    customer_session();
    if ($msg !== null) {
        $_SESSION['flash'] = [$msg, $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** Date UTC de la base, affichée dans le fuseau du navigateur du client (repli : UTC). */
function ldate(?string $utc, bool $time = true): string
{
    if (!$utc) {
        return '—';
    }
    $ts = strtotime($utc . ' UTC');
    return '<time class="lt" data-t="' . ($time ? 'dt' : 'd') . '" datetime="' . gmdate('c', $ts) . '">'
        . gmdate($time ? 'd/m/Y H:i' : 'd/m/Y', $ts) . '</time>';
}

function status_tag(string $s): string
{
    return '<span class="tag ' . h($s) . '">' . h(ORDER_STATUSES[$s] ?? $s) . '</span>';
}

/** Barre de suivi En attente → Livrée. */
function progress(string $status): string
{
    if ($status === 'cancelled') {
        return '<div class="cancelled">Cette commande a été annulée.</div>';
    }
    $steps = ['pending' => 'En attente', 'confirmed' => 'Confirmée', 'shipped' => 'Expédiée', 'delivered' => 'Livrée'];
    $idx = array_search($status, array_keys($steps), true);
    $out = '<ol class="steps">';
    $i = 0;
    foreach ($steps as $k => $label) {
        $cls = $i < $idx ? 'done' : ($i === $idx ? 'cur' : '');
        $out .= '<li class="' . $cls . '"><span>' . ($i < $idx ? '✓' : $i + 1) . '</span>' . h($label) . '</li>';
        $i++;
    }
    return $out . '</ol>';
}

const NOTIF_ICONS = [
    'welcome' => '👋', 'security' => '🔒', 'order_pending' => '🕒', 'order_confirmed' => '✅',
    'order_shipped' => '🚚', 'order_delivered' => '📦', 'order_cancelled' => '❌', 'info' => '🔔',
];

function cust_header(array $c, string $title, string $active = ''): void
{
    $unread = unread_notifications((int) $c['id']);
    $nav = [
        'index'         => ['🏠', 'Tableau de bord', ''],
        'orders'        => ['🛍️', 'Mes commandes', ''],
        'notifications' => ['🔔', 'Notifications', $unread ?: ''],
        'profile'       => ['👤', 'Mon profil', ''],
        'security'      => ['🔒', 'Sécurité', ''],
    ];
    ?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= h($title) ?> - Mon espace TechNova</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,Segoe UI,sans-serif;background:#f8faf9;color:#1a202c;font-size:14px}
a{color:#0e5a47}
.side{position:fixed;top:0;left:0;bottom:0;width:230px;background:#0a4335;color:#fff;padding:20px 14px;display:flex;flex-direction:column}
.side h2{margin:0 0 6px;font-size:20px}.side h2 span{color:#6ee7b7}
.who{font-size:12px;color:#a7d3c6;margin-bottom:20px;word-break:break-all}
.side a{display:flex;justify-content:space-between;align-items:center;color:#d0e7e1;text-decoration:none;padding:10px 12px;border-radius:10px;margin-bottom:4px}
.side a:hover,.side a.on{background:rgba(255,255,255,.12);color:#fff}
.side .bottom{margin-top:auto}
.side form{margin:0}.side button.lnk{width:100%;text-align:left;background:none;border:0;color:#d0e7e1;font:inherit;padding:10px 12px;border-radius:10px;cursor:pointer}
.side button.lnk:hover{background:rgba(255,255,255,.12);color:#fff}
.badge{background:#fbbf24;color:#000;border-radius:99px;padding:0 8px;font-size:11px;font-weight:700;line-height:20px}
.main{margin-left:230px;padding:28px;max-width:1100px}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap}
h1{margin:0;font-size:24px}
.bell{position:relative;text-decoration:none;background:#fff;border:1px solid #e5e7eb;border-radius:99px;padding:8px 14px;color:#1a202c;font-weight:600}
.bell .badge{margin-left:6px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px;margin-bottom:20px;overflow-x:auto}
.card h3{margin:0 0 12px;font-size:16px}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:10px 8px;border-bottom:1px solid #f0f0f0;vertical-align:top}
th{font-size:12px;text-transform:uppercase;color:#6b7280}
.btn{display:inline-block;background:#0e5a47;color:#fff;border:0;border-radius:10px;padding:9px 16px;font-weight:600;cursor:pointer;text-decoration:none;font-size:13px;font-family:inherit}
.btn:hover{background:#0a4335}.btn.sec{background:#e5e7eb;color:#111}.btn.red{background:#dc2626}.btn.sm{padding:5px 11px;font-size:12px}
input,select,textarea{width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:10px;font:inherit;background:#fff}
input[disabled]{background:#f3f4f6;color:#6b7280}
label{display:block;font-weight:600;margin:12px 0 4px;font-size:13px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px}
.grid .card{margin:0}
.stat{font-size:28px;font-weight:800;color:#0e5a47}.lbl{color:#6b7280}
.cols{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.flash{padding:12px 16px;border-radius:10px;margin-bottom:16px;background:#d1fae5;color:#065f46}.flash.err{background:#fee2e2;color:#991b1b}
.warn{padding:12px 16px;border-radius:10px;margin-bottom:16px;background:#fef3c7;color:#92400e}
.tag{padding:2px 10px;border-radius:99px;font-size:12px;font-weight:600;background:#e5e7eb;white-space:nowrap}
.tag.pending{background:#fef3c7}.tag.confirmed{background:#dbeafe}.tag.shipped{background:#e0e7ff}.tag.delivered{background:#d1fae5}.tag.cancelled{background:#fee2e2}
.row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.row form{display:inline}
.steps{list-style:none;display:flex;margin:14px 0;padding:0}
.steps li{flex:1;text-align:center;font-size:12px;color:#9ca3af;position:relative}
.steps li span{display:flex;width:28px;height:28px;margin:0 auto 6px;border-radius:50%;background:#e5e7eb;color:#6b7280;align-items:center;justify-content:center;font-weight:700;position:relative;z-index:1}
.steps li:not(:last-child)::after{content:"";position:absolute;top:13px;left:50%;width:100%;height:3px;background:#e5e7eb}
.steps li.done::after{background:#0e5a47}
.steps li.done span,.steps li.cur span{background:#0e5a47;color:#fff}.steps li.done,.steps li.cur{color:#0e5a47;font-weight:600}
.cancelled{padding:10px 14px;border-radius:10px;background:#fee2e2;color:#991b1b;margin:12px 0}
.notif{display:flex;gap:12px;padding:12px 8px;border-bottom:1px solid #f0f0f0;align-items:flex-start}
.notif:last-child{border-bottom:0}.notif .ic{font-size:20px;line-height:1.2}
.notif.unread{background:#f0fdf8;border-left:3px solid #0e5a47;padding-left:10px}
.notif .t{font-weight:700}.notif .m{color:#4b5563}.notif .d{color:#9ca3af;font-size:12px}.notif .grow{flex:1}
.muted{color:#6b7280}.empty{padding:24px;text-align:center;color:#6b7280}
@media(max-width:800px){.side{position:static;width:auto}.main{margin:0;padding:16px}.cols{grid-template-columns:1fr}}
</style></head><body>
<aside class="side"><h2>Tech<span>Nova</span></h2><div class="who"><?= h($c['name']) ?><br><?= h($c['email']) ?></div>
<?php foreach ($nav as $k => [$ico, $label, $count]): ?>
  <a href="<?= $k ?>.php" class="<?= $active === $k ? 'on' : '' ?>"><span><?= $ico ?> <?= h($label) ?></span><?php if ($count): ?><span class="badge"><?= (int) $count ?></span><?php endif; ?></a>
<?php endforeach; ?>
  <div class="bottom">
    <a href="../index.html">↗ Retour à la boutique</a>
    <form method="post" action="logout.php"><?= csrf_field() ?><button class="lnk">⎋ Se déconnecter</button></form>
  </div>
</aside><main class="main">
<div class="top"><h1><?= h($title) ?></h1>
  <a class="bell" href="notifications.php">🔔 Notifications<?php if ($unread): ?><span class="badge"><?= $unread ?></span><?php endif; ?></a></div>
<?php if ($f = flash()): ?><div class="flash <?= $f[1] === 'err' ? 'err' : '' ?>"><?= h($f[0]) ?></div><?php endif;
}

function cust_footer(): void
{
    echo <<<'HTML'
</main>
<script>
document.querySelectorAll('time.lt').forEach(function (t) {
  var d = new Date(t.getAttribute('datetime'));
  if (isNaN(d)) return;
  var o = t.dataset.t === 'd' ? {dateStyle: 'short'} : {dateStyle: 'short', timeStyle: 'short'};
  t.textContent = d.toLocaleString('fr-FR', o);
});
</script>
</body></html>
HTML;
}

/** Articles des commandes données : [order_id => [lignes]]. */
function order_items_for(array $orderIds): array
{
    if (!$orderIds) {
        return [];
    }
    $in = implode(',', array_fill(0, count($orderIds), '?'));
    $st = db()->prepare("SELECT order_id, product_name, unit_price, quantity FROM order_items WHERE order_id IN ($in) ORDER BY id");
    $st->execute(array_values($orderIds));
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[$r['order_id']][] = $r;
    }
    return $out;
}
