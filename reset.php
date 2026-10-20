<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$token = preg_match('/^[a-f0-9]{64}$/', $_GET['t'] ?? '') ? $_GET['t'] : '';
$state = 'form'; $err = '';

$find = function (string $t) {
    $st = db()->prepare("SELECT id FROM customers WHERE reset_token_hash = ? AND reset_expires > UTC_TIMESTAMP() AND is_active = 1");
    $st->execute([hash('sha256', $t)]);
    return $st->fetchColumn();
};

if ($token === '' || !$find($token)) {
    $state = 'invalid';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p1 = (string) ($_POST['password'] ?? '');
    $p2 = (string) ($_POST['password2'] ?? '');
    if (strlen($p1) < 8 || strlen($p1) > 200) {
        $err = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif ($p1 !== $p2) {
        $err = 'Les deux mots de passe ne correspondent pas.';
    } else {
        $id = $find($token);
        if ($id) {
            db()->prepare("UPDATE customers SET password_hash=?, reset_token_hash=NULL, reset_expires=NULL WHERE id=?")
                ->execute([password_hash($p1, PASSWORD_DEFAULT), $id]);
            notify((int) $id, 'security', 'Mot de passe réinitialisé', 'Votre mot de passe a été réinitialisé via le lien reçu par e-mail.', 'security.php');
            $state = 'done';
        } else {
            $state = 'invalid';
        }
    }
}
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Nouveau mot de passe - TechNova</title>
<style>body{font-family:system-ui,sans-serif;background:#f8faf9;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}
.c{background:#fff;border:1px solid #e5e7eb;border-radius:20px;padding:36px;width:380px;max-width:92vw}
h1{color:#0e5a47;margin-top:0;text-align:center}label{display:block;font-weight:600;margin:12px 0 4px;font-size:13px}
input{width:100%;padding:11px;border:1px solid #d1d5db;border-radius:10px;box-sizing:border-box}
button{width:100%;margin-top:20px;background:#0e5a47;color:#fff;border:0;padding:12px;border-radius:12px;font-weight:700;cursor:pointer}
.e{background:#fee2e2;color:#991b1b;padding:10px;border-radius:10px}a{color:#0e5a47}</style></head><body><div class="c">
<h1>Tech<span style="color:#12735b">Nova</span></h1>
<?php if ($state === 'done'): ?><p>Votre mot de passe a été modifié. Vous pouvez maintenant vous connecter.</p>
<?php elseif ($state === 'invalid'): ?><p>Ce lien est invalide ou a expiré. Refaites une demande depuis « Mot de passe oublié ».</p>
<?php else: ?>
<?php if ($err): ?><div class="e"><?= h($err) ?></div><?php endif; ?>
<form method="post"><label>Nouveau mot de passe (8 caractères min.)</label><input type="password" name="password" minlength="8" required autofocus>
<label>Confirmer le mot de passe</label><input type="password" name="password2" minlength="8" required><button>Enregistrer</button></form>
<?php endif; ?>
<p style="text-align:center"><a href="index.html">Retour au site</a></p></div></body></html>
