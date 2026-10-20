<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM contact_messages WHERE id=?")->execute([$id]);
        flash('Message supprimé.');
    } elseif ($action === 'unread') {
        $pdo->prepare("UPDATE contact_messages SET is_read=0 WHERE id=?")->execute([$id]);
        flash('Marqué comme non lu.');
    }
    header('Location: messages.php');
    exit;
}

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $st = $pdo->prepare("SELECT * FROM contact_messages WHERE id=?");
    $st->execute([$id]);
    $m = $st->fetch();
    if (!$m) { header('Location: messages.php'); exit; }
    $pdo->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?")->execute([$id]);
    admin_header('Message', 'messages');
?>
<div class="card"><p><b>De :</b> <?= h($m['name']) ?> &lt;<a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a>&gt;<br>
<b>Sujet :</b> <?= h($m['subject']) ?><br><b>Date :</b> <?= h($m['created_at']) ?> UTC</p>
<div style="white-space:pre-wrap;background:#f9fafb;border-radius:10px;padding:16px"><?= h($m['message']) ?></div>
<div class="row" style="margin-top:16px"><a class="btn" href="mailto:<?= h($m['email']) ?>?subject=<?= rawurlencode('Re: ' . $m['subject']) ?>">Répondre</a>
<form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="unread"><button class="btn sec">Marquer non lu</button></form>
<form method="post" onsubmit="return confirm('Supprimer ce message ?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete"><button class="btn red">Supprimer</button></form>
<a class="btn sec" href="messages.php">Retour</a></div></div>
<?php
    admin_footer();
    exit;
}

$total = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
[$page, $pages, $off] = paginate($total, 25, (int) ($_GET['p'] ?? 1));
$rows = $pdo->query("SELECT id, name, email, subject, is_read, created_at FROM contact_messages ORDER BY id DESC LIMIT 25 OFFSET $off")->fetchAll();
admin_header('Messages de contact', 'messages');
?>
<div class="card"><table><tr><th></th><th>De</th><th>Sujet</th><th>Date</th></tr>
<?php foreach ($rows as $m): ?>
<tr style="<?= $m['is_read'] ? '' : 'font-weight:700' ?>"><td><?= $m['is_read'] ? '' : '●' ?></td>
<td><?= h($m['name']) ?><br><small style="color:#6b7280;font-weight:400"><?= h($m['email']) ?></small></td>
<td><a href="?id=<?= (int) $m['id'] ?>"><?= h($m['subject']) ?></a></td><td style="font-weight:400"><?= h($m['created_at']) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="4">Aucun message.</td></tr><?php endif; ?></table>
<?php if ($pages > 1): ?><p class="row"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="btn <?= $i === $page ? '' : 'sec' ?>" href="?p=<?= $i ?>"><?= $i ?></a><?php endfor; ?></p><?php endif; ?></div>
<?php admin_footer();
