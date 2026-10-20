<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
$c = require_customer();
$pdo = db();
$cid = (int) $c['id'];

// Annulation par le client (uniquement tant que la commande est « En attente »).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ref = clean($_POST['ref'] ?? '', 20);
    $pdo->beginTransaction();
    $st = $pdo->prepare("SELECT id, status, total FROM orders WHERE reference=? AND customer_id=? FOR UPDATE");
    $st->execute([$ref, $cid]);
    $o = $st->fetch();
    if ($o && $o['status'] === 'pending') {
        $pdo->prepare("UPDATE products p JOIN order_items i ON i.product_id = p.id SET p.stock = p.stock + i.quantity WHERE i.order_id = ?")->execute([$o['id']]);
        $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=?")->execute([$o['id']]);
        $pdo->commit();
        notify($cid, 'order_cancelled', 'Commande annulée', "Vous avez annulé votre commande $ref.", 'orders.php?ref=' . $ref);
        send_mail((string) config('mail.to'), "[TechNova] Commande $ref annulée par le client", "Le client {$c['name']} ({$c['email']}) a annulé la commande $ref (" . fmt_price($o['total']) . ").\n");
        flash("Commande $ref annulée.");
    } else {
        $pdo->rollBack();
        flash('Cette commande ne peut plus être annulée : contactez-nous.', 'err');
    }
    header('Location: orders.php?ref=' . urlencode($ref));
    exit;
}

if (isset($_GET['ref'])) {
    $st = $pdo->prepare("SELECT * FROM orders WHERE reference=? AND customer_id=?");
    $st->execute([clean($_GET['ref'], 20), $cid]);
    $o = $st->fetch();
    if (!$o) {
        flash('Commande introuvable.', 'err');
        header('Location: orders.php');
        exit;
    }
    $items = order_items_for([$o['id']])[$o['id']] ?? [];
    cust_header($c, 'Commande ' . $o['reference'], 'orders');
?>
<div class="card">
  <div class="row" style="justify-content:space-between"><div>Passée le <?= ldate($o['created_at']) ?></div><?= status_tag($o['status']) ?></div>
  <?= progress($o['status']) ?>
</div>
<div class="cols">
  <div class="card"><h3>Articles</h3>
    <table><tr><th>Produit</th><th>Prix</th><th>Qté</th><th style="text-align:right">Sous-total</th></tr>
    <?php foreach ($items as $i): ?><tr><td><?= h($i['product_name']) ?></td><td><?= h(fmt_price($i['unit_price'])) ?></td><td><?= (int) $i['quantity'] ?></td><td style="text-align:right"><?= h(fmt_price($i['unit_price'] * $i['quantity'])) ?></td></tr><?php endforeach; ?>
    <tr><td colspan="3"><b>Total</b></td><td style="text-align:right"><b><?= h(fmt_price($o['total'])) ?></b></td></tr></table>
    <p class="muted">Paiement à la livraison.</p>
  </div>
  <div class="card"><h3>Livraison</h3>
    <p><b><?= h($o['customer_name']) ?></b><br><?= h($o['phone']) ?><br><?= h(trim($o['address'] . ' ' . $o['city'])) ?></p>
    <?php if ($o['status'] === 'pending'): ?>
    <form method="post" onsubmit="return confirm('Annuler cette commande ?')"><?= csrf_field() ?><input type="hidden" name="ref" value="<?= h($o['reference']) ?>">
      <button class="btn red">Annuler la commande</button></form>
    <p class="muted">L'annulation est possible tant que la commande n'est pas confirmée.</p>
    <?php endif; ?>
  </div>
</div>
<p><a class="btn sec" href="orders.php">← Mes commandes</a></p>
<?php
    cust_footer();
    exit;
}

$filter = $_GET['status'] ?? '';
$where = isset(ORDER_STATUSES[$filter]) ? ' AND status = ' . $pdo->quote($filter) : '';
$st = $pdo->prepare("SELECT * FROM orders WHERE customer_id=? $where ORDER BY id DESC LIMIT 100");
$st->execute([$cid]);
$rows = $st->fetchAll();
$items = order_items_for(array_column($rows, 'id'));

cust_header($c, 'Mes commandes', 'orders');
?>
<p class="row"><a class="btn <?= $filter ? 'sec' : '' ?>" href="orders.php">Toutes</a>
<?php foreach (ORDER_STATUSES as $k => $l): ?><a class="btn <?= $filter === $k ? '' : 'sec' ?>" href="?status=<?= $k ?>"><?= h($l) ?></a><?php endforeach; ?></p>
<?php foreach ($rows as $o): $its = $items[$o['id']] ?? []; ?>
<div class="card">
  <div class="row" style="justify-content:space-between"><div><b><a href="?ref=<?= h($o['reference']) ?>"><?= h($o['reference']) ?></a></b> · <?= ldate($o['created_at']) ?></div><?= status_tag($o['status']) ?></div>
  <?= progress($o['status']) ?>
  <div class="muted"><?= h(implode(', ', array_map(fn($i) => $i['product_name'] . ' ×' . $i['quantity'], $its))) ?></div>
  <p class="row" style="justify-content:space-between"><b><?= h(fmt_price($o['total'])) ?></b><a class="btn sm" href="?ref=<?= h($o['reference']) ?>">Détail</a></p>
</div>
<?php endforeach; if (!$rows): ?>
<div class="card empty">Aucune commande<?= $filter ? ' avec ce statut' : '' ?>.<br><br><a class="btn" href="../index.html">Aller à la boutique</a></div>
<?php endif;
cust_footer();
