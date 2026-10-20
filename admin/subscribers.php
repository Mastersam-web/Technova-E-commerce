<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_login();
$pdo = db();

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="abonnes-technova-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['email', 'actif', 'inscrit_le', 'desinscrit_le']);
    foreach ($pdo->query("SELECT email, is_active, subscribed_at, unsubscribed_at FROM newsletter_subscribers ORDER BY id") as $r) {
        $r['email'] = preg_match('/^[=+\-@]/', $r['email']) ? "'" . $r['email'] : $r['email']; // anti injection de formule
        fputcsv($out, [$r['email'], $r['is_active'] ? 'oui' : 'non', $r['subscribed_at'], $r['unsubscribed_at']]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare("DELETE FROM newsletter_subscribers WHERE id=?")->execute([(int) $_POST['id']]);
        flash('Abonné supprimé.');
    }
    header('Location: subscribers.php');
    exit;
}

$q = clean($_GET['q'] ?? '', 100);
$where = $q !== '' ? 'WHERE email LIKE ' . $pdo->quote('%' . addcslashes($q, '%_') . '%') : '';
$total = (int) $pdo->query("SELECT COUNT(*) FROM newsletter_subscribers $where")->fetchColumn();
[$page, $pages, $off] = paginate($total, 50, (int) ($_GET['p'] ?? 1));
$rows = $pdo->query("SELECT * FROM newsletter_subscribers $where ORDER BY id DESC LIMIT 50 OFFSET $off")->fetchAll();
admin_header('Abonnés newsletter (' . $total . ')', 'subscribers');
?>
<form class="row" method="get" style="margin-bottom:16px"><input name="q" placeholder="Rechercher un e-mail" value="<?= h($q) ?>" style="max-width:300px">
<button class="btn">Rechercher</button><a class="btn sec" href="?export=1">Exporter CSV</a></form>
<div class="card"><table><tr><th>E-mail</th><th>Statut</th><th>Inscrit le</th><th></th></tr>
<?php foreach ($rows as $s): ?>
<tr><td><?= h($s['email']) ?></td><td><?= $s['is_active'] ? 'Actif' : 'Désinscrit' ?></td><td><?= h($s['subscribed_at']) ?></td>
<td><form method="post" onsubmit="return confirm('Supprimer cet abonné ?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn red">Supprimer</button></form></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="4">Aucun abonné.</td></tr><?php endif; ?></table>
<?php if ($pages > 1): ?><p class="row"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="btn <?= $i === $page ? '' : 'sec' ?>" href="?p=<?= $i ?>&q=<?= h(urlencode($q)) ?>"><?= $i ?></a><?php endfor; ?></p><?php endif; ?></div>
<?php admin_footer();
