<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
$c = require_customer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = clean($_POST['name'] ?? '', 120);
    $phone = clean($_POST['phone'] ?? '', 30);
    $address = clean($_POST['address'] ?? '', 255);
    $city = clean($_POST['city'] ?? '', 100);
    if (mb_strlen($name) < 2) {
        flash('Nom invalide.', 'err');
    } elseif ($phone !== '' && !preg_match('/^[0-9+()\s.-]{6,30}$/', $phone)) {
        flash('Numéro de téléphone invalide.', 'err');
    } else {
        db()->prepare("UPDATE customers SET name=?, phone=?, address=?, city=? WHERE id=?")->execute([$name, $phone, $address, $city, $c['id']]);
        flash('Profil mis à jour.');
    }
    header('Location: profile.php');
    exit;
}

cust_header($c, 'Mon profil', 'profile');
?>
<div class="card" style="max-width:620px"><form method="post"><?= csrf_field() ?>
  <label>Nom complet</label><input name="name" value="<?= h($c['name']) ?>" required maxlength="120">
  <label>E-mail (identifiant de connexion)</label><input value="<?= h($c['email']) ?>" disabled>
  <label>Téléphone</label><input name="phone" value="<?= h($c['phone']) ?>" maxlength="30">
  <label>Adresse de livraison</label><input name="address" value="<?= h($c['address']) ?>" maxlength="255">
  <label>Ville</label><input name="city" value="<?= h($c['city']) ?>" maxlength="100">
  <p><button class="btn">Enregistrer</button></p>
</form></div>
<?php cust_footer();
