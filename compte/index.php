<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
$c = require_customer();
$pdo = db();
$cid = (int) $c['id'];

$one = function (string $sql) use ($pdo, $cid) {
    $st = $pdo->prepare($sql);
    $st->execute([$cid]);
    return $st->fetchColumn();
};
$nbOrders = (int) $one("SELECT COUNT(*) FROM orders WHERE customer_id=?");
$nbOpen   = (int) $one("SELECT COUNT(*) FROM orders WHERE customer_id=? AND status IN ('pending','confirmed','shipped')");
$nbDone   = (int) $one("SELECT COUNT(*) FROM orders WHERE customer_id=? AND status='delivered'");
$spent    = (float) $one("SELECT COALESCE(SUM(total),0) FROM orders WHERE customer_id=? AND status<>'cancelled'");
$unread   = unread_notifications($cid);

$st = $pdo->prepare("SELECT * FROM orders WHERE customer_id=? ORDER BY id DESC LIMIT 1");
$st->execute([$cid]);
$last = $st->fetch();
$lastItems = $last ? (order_items_for([$last['id']])[$last['id']] ?? []) : [];

$st = $pdo->prepare("SELECT * FROM notifications WHERE customer_id=? ORDER BY id DESC LIMIT 5");
$st->execute([$cid]);
$notifs = $st->fetchAll();

$st = $pdo->prepare("SELECT created_at, last_login FROM customers WHERE id=?");
$st->execute([$cid]);
$meta = $st->fetch();
$incomplete = $c['phone'] === '' || $c['address'] === '' || $c['city'] === '';

cust_header($c, 'Bonjour, ' . explode(' ', trim($c['name']))[0] . ' 👋', 'index');
if ($incomplete): ?>
<div class="warn">Votre profil est incomplet (téléphone, adresse ou ville). <a href="profile.php">Complétez-le</a> pour commander plus vite.</div>
<?php endif; ?>
<div class="grid">
  <div class="card"><div class="lbl">Commandes</div><div class="stat"><?= $nbOrders ?></div></div>
  <div class="card"><div class="lbl">En cours</div><div class="stat"><?= $nbOpen ?></div></div>
  <div class="card"><div class="lbl">Livrées</div><div class="stat"><?= $nbDone ?></div></div>
  <div class="card"><div class="lbl">Total dépensé</div><div class="stat" style="font-size:22px"><?= h(fmt_price($spent)) ?></div></div>
  <a class="card" href="notifications.php" style="text-decoration:none;color:inherit"><div class="lbl">Notifications non lues</div><div class="stat"><?= $unread ?></div></a>
</div>

<div class="card"><h3>Ma dernière commande</h3>
<?php if ($last): ?>
  <div class="row" style="justify-content:space-between"><div><b><a href="orders.php?ref=<?= h($last['reference']) ?>"><?= h($last['reference']) ?></a></b> · <?= ldate($last['created_at']) ?></div><?= status_tag($last['status']) ?></div>
  <?= progress($last['status']) ?>
  <table><?php foreach ($lastItems as $i): ?><tr><td><?= h($i['product_name']) ?> × <?= (int) $i['quantity'] ?></td><td style="text-align:right"><?= h(fmt_price($i['unit_price'] * $i['quantity'])) ?></td></tr><?php endforeach; ?>
  <tr><td><b>Total</b></td><td style="text-align:right"><b><?= h(fmt_price($last['total'])) ?></b></td></tr></table>
  <p class="row"><a class="btn" href="orders.php?ref=<?= h($last['reference']) ?>">Voir le détail</a><a class="btn sec" href="orders.php">Toutes mes commandes</a></p>
<?php else: ?>
  <div class="empty">Vous n'avez pas encore passé de commande.<br><br><a class="btn" href="../index.html">Découvrir la boutique</a></div>
<?php endif; ?></div>

<div class="cols">
  <div class="card"><h3>Dernières notifications</h3>
  <?php foreach ($notifs as $n): ?>
    <div class="notif <?= $n['is_read'] ? '' : 'unread' ?>"><div class="ic"><?= NOTIF_ICONS[$n['type']] ?? '🔔' ?></div>
    <div class="grow"><div class="t"><?= h($n['title']) ?></div><div class="m"><?= h($n['message']) ?></div><div class="d"><?= ldate($n['created_at']) ?></div></div></div>
  <?php endforeach; if (!$notifs): ?><div class="empty">Aucune notification.</div><?php endif; ?>
  <?php if ($notifs): ?><p><a href="notifications.php">Voir toutes les notifications →</a></p><?php endif; ?></div>

  <div class="card"><h3>Mes informations</h3>
    <p><b><?= h($c['name']) ?></b><br><?= h($c['email']) ?><br><?= h($c['phone'] ?: 'Téléphone non renseigné') ?></p>
    <p class="muted">Livraison :<br><span style="color:#1a202c"><?= h(trim($c['address'] . ' ' . $c['city']) ?: 'Adresse non renseignée') ?></span></p>
    <p class="muted">Membre depuis le <?= ldate($meta['created_at'], false) ?><br>Dernière connexion : <?= ldate($meta['last_login']) ?></p>
    <a class="btn sec" href="profile.php">Modifier mon profil</a>
  </div>
</div>
<?php cust_footer();
