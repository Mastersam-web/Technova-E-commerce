<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_login();

$pdo = db();
$one = fn(string $sql) => $pdo->query($sql)->fetchColumn();
$stats = [
    'Commandes en attente' => (int) $one("SELECT COUNT(*) FROM orders WHERE status='pending'"),
    'Commandes (total)'    => (int) $one("SELECT COUNT(*) FROM orders"),
    "Chiffre d'affaires"   => fmt_price($one("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ('confirmed','shipped','delivered')")),
    'Messages non lus'     => (int) $one("SELECT COUNT(*) FROM contact_messages WHERE is_read=0"),
    'Abonnés actifs'       => (int) $one("SELECT COUNT(*) FROM newsletter_subscribers WHERE is_active=1"),
    'Clients inscrits'     => (int) $one("SELECT COUNT(*) FROM customers"),
    'Produits actifs'      => (int) $one("SELECT COUNT(*) FROM products WHERE is_active=1"),
];
$recent = $pdo->query("SELECT id, reference, customer_name, total, status, created_at FROM orders ORDER BY id DESC LIMIT 8")->fetchAll();
$low = $pdo->query("SELECT name, stock FROM products WHERE is_active=1 AND stock<=5 ORDER BY stock LIMIT 8")->fetchAll();

admin_header('Tableau de bord', 'index');
?>
<div class="grid">
<?php foreach ($stats as $label => $val): ?>
  <div class="card"><div style="color:#6b7280"><?= h($label) ?></div><div class="stat"><?= h($val) ?></div></div>
<?php endforeach; ?>
</div>
<div class="card"><h3 style="margin-top:0">Dernières commandes</h3>
<table><tr><th>Réf.</th><th>Client</th><th>Total</th><th>Statut</th><th>Date</th></tr>
<?php foreach ($recent as $o): ?>
<tr><td><a href="orders.php?id=<?= (int) $o['id'] ?>"><?= h($o['reference']) ?></a></td><td><?= h($o['customer_name']) ?></td>
<td><?= h(fmt_price($o['total'])) ?></td><td><span class="tag <?= h($o['status']) ?>"><?= h($o['status']) ?></span></td><td><?= h($o['created_at']) ?> UTC</td></tr>
<?php endforeach; if (!$recent): ?><tr><td colspan="5">Aucune commande pour le moment.</td></tr><?php endif; ?>
</table></div>
<?php if ($low): ?><div class="card"><h3 style="margin-top:0">Stock faible</h3>
<?php foreach ($low as $p): ?><div><?= h($p['name']) ?> — <b><?= (int) $p['stock'] ?></b> restant(s)</div><?php endforeach; ?></div><?php endif;
admin_footer();
