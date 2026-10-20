<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$token = preg_match('/^[a-f0-9]{40}$/', $_GET['t'] ?? '') ? $_GET['t'] : '';
$done = false;
if ($token !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare("UPDATE newsletter_subscribers SET is_active=0, unsubscribed_at=NOW() WHERE token=?");
    $st->execute([$token]);
    $done = $st->rowCount() > 0;
}
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Désinscription - TechNova</title>
<style>body{font-family:system-ui,sans-serif;background:#f8faf9;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}
.c{background:#fff;border:1px solid #e5e7eb;border-radius:20px;padding:40px;max-width:420px;text-align:center}
button{background:#0e5a47;color:#fff;border:0;padding:12px 28px;border-radius:12px;font-weight:600;cursor:pointer}
a{color:#0e5a47}</style></head><body><div class="c">
<h1 style="color:#0e5a47">Tech<span style="color:#12735b">Nova</span></h1>
<?php if ($done): ?>
  <p>Vous avez été désinscrit(e) de la newsletter.</p>
<?php elseif ($token === ''): ?>
  <p>Lien de désinscription invalide.</p>
<?php else: ?>
  <p>Confirmez-vous votre désinscription de la newsletter ?</p>
  <form method="post"><button type="submit">Me désinscrire</button></form>
<?php endif; ?>
<p><a href="index.html">Retour au site</a></p></div></body></html>
