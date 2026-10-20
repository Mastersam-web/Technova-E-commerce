<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_login();
$pdo = db();
$statuses = ['pending' => 'En attente', 'confirmed' => 'Confirmée', 'shipped' => 'Expédiée', 'delivered' => 'Livrée', 'cancelled' => 'Annulée'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $new = $_POST['status'] ?? '';
    if (isset($statuses[$new])) {
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT status, customer_id, reference, customer_email, customer_name FROM orders WHERE id=? FOR UPDATE");
        $st->execute([$id]);
        $ord = $st->fetch();
        $old = $ord ? $ord['status'] : false;
        if ($old !== false && $old !== $new) {
            // Annulation => remise en stock ; réactivation d'une commande annulée => nouveau décrément
            $sign = null;
            if ($new === 'cancelled') $sign = '+';
            elseif ($old === 'cancelled') $sign = '-';
            if ($sign) {
                $pdo->prepare("UPDATE products p JOIN order_items i ON i.product_id = p.id
                               SET p.stock = " . ($sign === '+' ? 'p.stock + i.quantity' : 'GREATEST(0, CAST(p.stock AS SIGNED) - i.quantity)') . "
                               WHERE i.order_id = ?")->execute([$id]);
            }
            $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$new, $id]);
        }
        $pdo->commit();
        if ($ord && $old !== $new && $ord['customer_id']) {
            notify_order_status((int) $ord['customer_id'], $ord['reference'], $new, (string) $ord['customer_email'], (string) $ord['customer_name']);
        }
        flash('Statut mis à jour.');
    }
    header('Location: orders.php?id=' . $id);
    exit;
}

if (isset($_GET['id'])) {
    $st = $pdo->prepare("SELECT * FROM orders WHERE id=?");
    $st->execute([(int) $_GET['id']]);
    $o = $st->fetch();
    if (!$o) { header('Location: orders.php'); exit; }
    $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
    $items->execute([$o['id']]);
    $items = $items->fetchAll();
    admin_header('Commande ' . $o['reference'], 'orders');
?>
<div class="card">
<p><b>Client :</b> <?= h($o['customer_name']) ?><br><b>Téléphone :</b> <a href="tel:<?= h($o['phone']) ?>"><?= h($o['phone']) ?></a><br>
<?php if ($o['customer_email']): ?><b>E-mail :</b> <?= h($o['customer_email']) ?><br><?php endif; ?>
<?php if ($o['customer_id']): ?><b>Compte client :</b> <a href="customers.php?q=<?= h(urlencode((string) $o['customer_email'])) ?>">#<?= (int) $o['customer_id'] ?></a><br><?php endif; ?>
<b>Adresse :</b> <?= h($o['address']) ?> <?= h($o['city']) ?><br><b>Date :</b> <?= h($o['created_at']) ?> UTC</p>
<table><tr><th>Produit</th><th>Prix unitaire</th><th>Qté</th><th>Sous-total</th></tr>
<?php foreach ($items as $i): ?><tr><td><?= h($i['product_name']) ?></td><td><?= h(fmt_price($i['unit_price'])) ?></td><td><?= (int) $i['quantity'] ?></td><td><?= h(fmt_price($i['unit_price'] * $i['quantity'])) ?></td></tr><?php endforeach; ?>
<tr><td colspan="3" style="text-align:right"><b>Total</b></td><td><b><?= h(fmt_price($o['total'])) ?></b></td></tr></table>
<form method="post" class="row" style="margin-top:16px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
<select name="status" style="width:auto"><?php foreach ($statuses as $k => $l): ?><option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select>
<button class="btn">Mettre à jour</button><a class="btn sec" href="orders.php">Retour</a></form></div>
<?php
    admin_footer();
    exit;
}

$filter = $_GET['status'] ?? '';
$where = isset($statuses[$filter]) ? 'WHERE status = ' . $pdo->quote($filter) : '';
$total = (int) $pdo->query("SELECT COUNT(*) FROM orders $where")->fetchColumn();
[$page, $pages, $off] = paginate($total, 25, (int) ($_GET['p'] ?? 1));
$rows = $pdo->query("SELECT * FROM orders $where ORDER BY id DESC LIMIT 25 OFFSET $off")->fetchAll();
admin_header('Commandes', 'orders');
?>
<p class="row"><a class="btn <?= $filter ? 'sec' : '' ?>" href="orders.php">Toutes</a>
<?php foreach ($statuses as $k => $l): ?><a class="btn <?= $filter === $k ? '' : 'sec' ?>" href="?status=<?= $k ?>"><?= h($l) ?></a><?php endforeach; ?></p>
<div class="card"><table><tr><th>Réf.</th><th>Client</th><th>Téléphone</th><th>Total</th><th>Statut</th><th>Date</th></tr>
<?php foreach ($rows as $o): ?>
<tr><td><a href="?id=<?= (int) $o['id'] ?>"><?= h($o['reference']) ?></a></td><td><?= h($o['customer_name']) ?></td><td><?= h($o['phone']) ?></td>
<td><?= h(fmt_price($o['total'])) ?></td><td><span class="tag <?= h($o['status']) ?>"><?= h($statuses[$o['status']]) ?></span></td><td><?= h($o['created_at']) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6">Aucune commande.</td></tr><?php endif; ?></table>
<?php if ($pages > 1): ?><p class="row"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="btn <?= $i === $page ? '' : 'sec' ?>" href="?p=<?= $i ?><?= $filter ? '&status=' . h($filter) : '' ?>"><?= $i ?></a><?php endfor; ?></p><?php endif; ?></div>
<?php admin_footer();
