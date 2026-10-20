<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
admin_session();

if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!rate_limit('login', (int) config('limits.login_per_15min', 8), 900)) {
        $error = 'Trop de tentatives. Réessayez dans 15 minutes.';
    } else {
        $st = db()->prepare("SELECT id, password_hash FROM admins WHERE username = ?");
        $st->execute([clean($_POST['username'] ?? '', 50)]);
        $a = $st->fetch();
        // password_verify exécuté même si l'utilisateur n'existe pas (évite l'énumération par timing)
        $hash = $a['password_hash'] ?? DUMMY_HASH;
        if (password_verify((string) ($_POST['password'] ?? ''), $hash) && $a) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $a['id'];
            header('Location: index.php');
            exit;
        }
        $error = 'Identifiants incorrects.';
    }
}
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Connexion - Admin TechNova</title>
<style>body{font-family:system-ui,sans-serif;background:#f8faf9;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}
.c{background:#fff;border:1px solid #e5e7eb;border-radius:20px;padding:36px;width:360px}
h1{color:#0e5a47;margin:0 0 20px;text-align:center}label{display:block;font-weight:600;margin:12px 0 4px;font-size:13px}
input{width:100%;padding:11px 12px;border:1px solid #d1d5db;border-radius:10px;font:inherit}
button{width:100%;margin-top:20px;background:#0e5a47;color:#fff;border:0;padding:12px;border-radius:12px;font-weight:700;cursor:pointer}
.e{background:#fee2e2;color:#991b1b;padding:10px;border-radius:10px;margin-bottom:8px}</style></head><body>
<form class="c" method="post" autocomplete="off"><h1>Tech<span style="color:#12735b">Nova</span> admin</h1>
<?php if ($error): ?><div class="e"><?= h($error) ?></div><?php endif; ?>
<?= csrf_field() ?>
<label>Identifiant</label><input name="username" required autofocus>
<label>Mot de passe</label><input name="password" type="password" required>
<button type="submit">Se connecter</button></form></body></html>
