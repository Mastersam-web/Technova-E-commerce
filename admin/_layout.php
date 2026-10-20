<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

function admin_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('technova_admin');
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

function require_login(): void
{
    admin_session();
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string
{
    admin_session();
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
    admin_session();
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
    if (!$ok) {
        http_response_code(419);
        exit('Session expirée ou requête invalide. Rechargez la page.');
    }
}

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    admin_session();
    if ($msg !== null) {
        $_SESSION['flash'] = [$msg, $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function admin_header(string $title, string $active = ''): void
{
    $unread = (int) db()->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();
    $pending = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
    $nav = [
        'index'       => ['Tableau de bord', ''],
        'products'    => ['Produits', ''],
        'orders'      => ['Commandes', $pending ?: ''],
        'customers'   => ['Clients', ''],
        'messages'    => ['Messages', $unread ?: ''],
        'subscribers' => ['Abonnés', ''],
    ];
    ?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= h($title) ?> - Admin TechNova</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,Segoe UI,sans-serif;background:#f8faf9;color:#1a202c;font-size:14px}
a{color:#0e5a47}
.side{position:fixed;top:0;left:0;bottom:0;width:220px;background:#0a4335;color:#fff;padding:20px 14px}
.side h2{margin:0 0 24px;font-size:20px}.side h2 span{color:#6ee7b7}
.side a{display:flex;justify-content:space-between;color:#d0e7e1;text-decoration:none;padding:10px 12px;border-radius:10px;margin-bottom:4px}
.side a:hover,.side a.on{background:rgba(255,255,255,.12);color:#fff}
.badge{background:#fbbf24;color:#000;border-radius:99px;padding:0 8px;font-size:11px;font-weight:700;line-height:20px}
.main{margin-left:220px;padding:28px;max-width:1200px}
h1{margin:0 0 20px;font-size:24px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px;margin-bottom:20px;overflow-x:auto}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:10px 8px;border-bottom:1px solid #f0f0f0;vertical-align:top}
th{font-size:12px;text-transform:uppercase;color:#6b7280}
.btn{display:inline-block;background:#0e5a47;color:#fff;border:0;border-radius:10px;padding:9px 16px;font-weight:600;cursor:pointer;text-decoration:none;font-size:13px}
.btn:hover{background:#0a4335}.btn.sec{background:#e5e7eb;color:#111}.btn.red{background:#dc2626}
input,select,textarea{width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:10px;font:inherit;background:#fff}
label{display:block;font-weight:600;margin:12px 0 4px;font-size:13px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}
.stat{font-size:30px;font-weight:800;color:#0e5a47}
.flash{padding:12px 16px;border-radius:10px;margin-bottom:16px;background:#d1fae5;color:#065f46}.flash.err{background:#fee2e2;color:#991b1b}
.tag{padding:2px 10px;border-radius:99px;font-size:12px;font-weight:600;background:#e5e7eb}
.tag.pending{background:#fef3c7}.tag.confirmed{background:#dbeafe}.tag.shipped{background:#e0e7ff}.tag.delivered{background:#d1fae5}.tag.cancelled{background:#fee2e2}
img.th{width:48px;height:48px;object-fit:cover;border-radius:8px}
.row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.row form{display:inline}
@media(max-width:800px){.side{position:static;width:auto}.main{margin:0;padding:16px}}
</style></head><body>
<aside class="side"><h2>Tech<span>Nova</span> admin</h2>
<?php foreach ($nav as $k => [$label, $count]): ?>
  <a href="<?= $k ?>.php" class="<?= $active === $k ? 'on' : '' ?>"><span><?= h($label) ?></span><?php if ($count): ?><span class="badge"><?= (int) $count ?></span><?php endif; ?></a>
<?php endforeach; ?>
  <a href="../index.html" target="_blank">↗ Voir le site</a>
  <a href="logout.php">Déconnexion</a>
</aside><main class="main"><h1><?= h($title) ?></h1>
<?php if ($f = flash()): ?><div class="flash <?= $f[1] === 'err' ? 'err' : '' ?>"><?= h($f[0]) ?></div><?php endif;
}

function admin_footer(): void
{
    echo '</main></body></html>';
}

function paginate(int $total, int $per, int $page): array
{
    $pages = max(1, (int) ceil($total / $per));
    $page = min(max(1, $page), $pages);
    return [$page, $pages, ($page - 1) * $per];
}
