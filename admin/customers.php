<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'toggle') {
        $pdo->prepare("UPDATE customers SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
        flash('Statut du compte mis à jour.');
    }
    header('Location: customers.php');
    exit;
}

$q = clean($_GET['q'] ?? '', 100);
$where = $q !== '' ? 'WHERE c.email LIKE ' . $pdo->quote('%' . addcslashes($q, '%_') . '%') . ' OR c.name LIKE ' . $pdo->quote('%' . addcslashes($q, '%_') . '%') : '';
$total = (int) $pdo->query("SELECT COUNT(*) FROM customers c $where")->fetchColumn();
[$page, $pages, $off] = paginate($total, 50, (int) ($_GET['p'] ?? 1));
$rows = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS nb_orders,
                            (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.customer_id = c.id AND o.status <> 'cancelled') AS spent
                       FROM customers c $where ORDER BY c.id DESC LIMIT 50 OFFSET $off")->fetchAll();
admin_header('Clients (' . $total . ')', 'customers');
?>
<form class="row" method="get" style="margin-bottom:16px"><input name="q" placeholder="Rechercher nom ou e-mail" value="<?= h($q) ?>" style="max-width:300px"><button class="btn">Rechercher</button></form>
<div class="card"><table><tr><th>Client</th><th>Téléphone</th><th>Commandes</th><th>Total dépensé</th><th>Inscrit le</th><th>État</th><th></th></tr>
<?php foreach ($rows as $c): ?>
<tr><td><b><?= h($c['name']) ?></b><br><small style="color:#6b7280"><?= h($c['email']) ?></small></td><td><?= h($c['phone']) ?></td>
<td><?= (int) $c['nb_orders'] ?></td><td><?= h(fmt_price($c['spent'])) ?></td><td><?= h($c['created_at']) ?></td>
<td><?= $c['is_active'] ? 'Actif' : 'Suspendu' ?></td>
<td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
<button class="btn <?= $c['is_active'] ? 'red' : 'sec' ?>"><?= $c['is_active'] ? 'Suspendre' : 'Réactiver' ?></button></form></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7">Aucun client.</td></tr><?php endif; ?></table>
<?php if ($pages > 1): ?><p class="row"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="btn <?= $i === $page ? '' : 'sec' ?>" href="?p=<?= $i ?>&q=<?= h(urlencode($q)) ?>"><?= $i ?></a><?php endfor; ?></p><?php endif; ?></div>
<?php admin_footer();
