<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
$c = require_customer();
$pdo = db();
$cid = (int) $c['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($act === 'read_all') {
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE customer_id=?")->execute([$cid]);
        flash('Toutes les notifications sont marquées comme lues.');
    } elseif ($act === 'read') {
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND customer_id=?")->execute([$id, $cid]);
    } elseif ($act === 'delete') {
        $pdo->prepare("DELETE FROM notifications WHERE id=? AND customer_id=?")->execute([$id, $cid]);
    } elseif ($act === 'delete_read') {
        $pdo->prepare("DELETE FROM notifications WHERE customer_id=? AND is_read=1")->execute([$cid]);
        flash('Notifications lues supprimées.');
    }
    header('Location: notifications.php' . (($_GET['f'] ?? '') === 'unread' ? '?f=unread' : ''));
    exit;
}

// Ouvrir une notification : la marque comme lue puis redirige vers sa cible.
if (isset($_GET['open'])) {
    $st = $pdo->prepare("SELECT link FROM notifications WHERE id=? AND customer_id=?");
    $st->execute([(int) $_GET['open'], $cid]);
    $link = $st->fetchColumn();
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND customer_id=?")->execute([(int) $_GET['open'], $cid]);
    // lien interne uniquement (relatif à /compte/)
    header('Location: ' . (is_string($link) && preg_match('/^[a-z]+\.php(\?[A-Za-z0-9=&_-]*)?$/', $link) ? $link : 'notifications.php'));
    exit;
}

$unreadOnly = ($_GET['f'] ?? '') === 'unread';
$st = $pdo->prepare("SELECT * FROM notifications WHERE customer_id=?" . ($unreadOnly ? ' AND is_read=0' : '') . " ORDER BY id DESC LIMIT 100");
$st->execute([$cid]);
$rows = $st->fetchAll();

cust_header($c, 'Notifications', 'notifications');
?>
<div class="row" style="margin-bottom:16px;justify-content:space-between">
  <div class="row"><a class="btn <?= $unreadOnly ? 'sec' : '' ?>" href="notifications.php">Toutes</a><a class="btn <?= $unreadOnly ? '' : 'sec' ?>" href="?f=unread">Non lues</a></div>
  <div class="row">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn sec">Tout marquer comme lu</button></form>
    <form method="post" onsubmit="return confirm('Supprimer toutes les notifications lues ?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_read"><button class="btn sec">Supprimer les lues</button></form>
  </div>
</div>
<div class="card">
<?php foreach ($rows as $n): ?>
  <div class="notif <?= $n['is_read'] ? '' : 'unread' ?>"><div class="ic"><?= NOTIF_ICONS[$n['type']] ?? '🔔' ?></div>
    <div class="grow"><div class="t"><?= h($n['title']) ?></div><div class="m"><?= h($n['message']) ?></div><div class="d"><?= ldate($n['created_at']) ?></div></div>
    <div class="row">
      <?php if ($n['link']): ?><a class="btn sm" href="?open=<?= (int) $n['id'] ?>">Voir</a><?php endif; ?>
      <?php if (!$n['is_read']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><button class="btn sm sec">Lu</button></form><?php endif; ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><button class="btn sm sec" title="Supprimer">✕</button></form>
    </div></div>
<?php endforeach; if (!$rows): ?><div class="empty"><?= $unreadOnly ? 'Aucune notification non lue.' : 'Aucune notification pour le moment.' ?></div><?php endif; ?>
</div>
<?php cust_footer();
