<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
$c = require_customer();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cur = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    $new2 = (string) ($_POST['new2'] ?? '');
    $st = $pdo->prepare("SELECT password_hash FROM customers WHERE id=?");
    $st->execute([$c['id']]);
    if (!rate_limit('customer_pwd', 10, 900)) {
        flash('Trop de tentatives. Réessayez dans 15 minutes.', 'err');
    } elseif (!password_verify($cur, (string) $st->fetchColumn())) {
        flash('Mot de passe actuel incorrect.', 'err');
    } elseif (strlen($new) < 8 || strlen($new) > 200) {
        flash('Le nouveau mot de passe doit contenir au moins 8 caractères.', 'err');
    } elseif ($new !== $new2) {
        flash('Les deux nouveaux mots de passe ne correspondent pas.', 'err');
    } else {
        $pdo->prepare("UPDATE customers SET password_hash=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $c['id']]);
        session_regenerate_id(true);
        notify((int) $c['id'], 'security', 'Mot de passe modifié', "Votre mot de passe vient d'être changé. Si ce n'est pas vous, réinitialisez-le immédiatement.", 'security.php');
        flash('Mot de passe modifié.');
    }
    header('Location: security.php');
    exit;
}

$st = $pdo->prepare("SELECT created_at, last_login FROM customers WHERE id=?");
$st->execute([$c['id']]);
$meta = $st->fetch();
cust_header($c, 'Sécurité', 'security');
?>
<div class="cols">
<div class="card"><h3>Changer mon mot de passe</h3><form method="post"><?= csrf_field() ?>
  <label>Mot de passe actuel</label><input type="password" name="current" required autocomplete="current-password">
  <label>Nouveau mot de passe (8 caractères min.)</label><input type="password" name="new" required minlength="8" autocomplete="new-password">
  <label>Confirmer le nouveau mot de passe</label><input type="password" name="new2" required minlength="8" autocomplete="new-password">
  <p><button class="btn">Modifier</button></p></form></div>
<div class="card"><h3>Mon compte</h3>
  <p>Compte créé le <?= ldate($meta['created_at'], false) ?><br>Dernière connexion : <?= ldate($meta['last_login']) ?></p>
  <p class="muted">Vous recevez une notification à chaque changement de mot de passe. Mot de passe oublié ? Utilisez « Mot de passe oublié » dans la fenêtre de connexion du site.</p>
</div></div>
<?php cust_footer();
