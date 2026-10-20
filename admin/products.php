<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_login();
$pdo = db();

function slugify(string $s): string
{
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s) ?? '', '-'));
    return $s !== '' ? mb_substr($s, 0, 90) : 'produit-' . bin2hex(random_bytes(3));
}

/** Gère l'upload d'image ; retourne le chemin relatif ou null. */
function handle_upload(): ?string
{
    if (empty($_FILES['image_file']['name']) || $_FILES['image_file']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES['image_file'];
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('Image refusée (3 Mo maximum).');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext || !@getimagesize($f['tmp_name'])) {
        throw new RuntimeException('Format d\'image non supporté (JPG, PNG ou WebP).');
    }
    $dir = dirname(__DIR__) . '/uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) {
        throw new RuntimeException('Impossible d\'enregistrer l\'image.');
    }
    return 'uploads/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'delete') {
            $pdo->prepare("DELETE FROM products WHERE id=?")->execute([(int) $_POST['id']]);
            flash('Produit supprimé.');
        } elseif ($action === 'toggle') {
            $pdo->prepare("UPDATE products SET is_active = 1 - is_active WHERE id=?")->execute([(int) $_POST['id']]);
            flash('Visibilité mise à jour.');
        } elseif ($action === 'save') {
            $id    = (int) ($_POST['id'] ?? 0);
            $name  = clean($_POST['name'] ?? '', 150);
            $cat   = (int) ($_POST['category_id'] ?? 0);
            $price = round((float) str_replace(',', '.', (string) ($_POST['price'] ?? 0)), 2);
            $oldRaw = trim((string) ($_POST['old_price'] ?? ''));
            $old   = $oldRaw === '' ? null : round((float) str_replace(',', '.', $oldRaw), 2);
            $rating  = min(5, max(1, (int) ($_POST['rating'] ?? 5)));
            $reviews = max(0, (int) ($_POST['reviews'] ?? 0));
            $stock   = max(0, (int) ($_POST['stock'] ?? 0));
            $feat  = isset($_POST['is_featured']) ? 1 : 0;
            $act   = isset($_POST['is_active']) ? 1 : 0;
            $image = clean($_POST['image'] ?? '', 500);
            if ($image !== '' && !preg_match('#^(https://|/|uploads/)#i', $image)) {
                throw new RuntimeException('L\'URL de l\'image doit commencer par https:// ou uploads/.');
            }
            if ($name === '' || $price <= 0 || $cat <= 0) {
                throw new RuntimeException('Nom, catégorie et prix (> 0) sont obligatoires.');
            }
            if ($old !== null && $old <= 0) $old = null;
            if ($up = handle_upload()) {
                $image = $up;
            }
            if ($id > 0) {
                $pdo->prepare("UPDATE products SET name=?, category_id=?, price=?, old_price=?, rating=?, reviews=?, stock=?, is_featured=?, is_active=?, image=? WHERE id=?")
                    ->execute([$name, $cat, $price, $old, $rating, $reviews, $stock, $feat, $act, $image, $id]);
                flash('Produit mis à jour.');
            } else {
                $slug = slugify($name);
                $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug=?");
                $base = $slug; $i = 2;
                while (true) { $chk->execute([$slug]); if (!(int) $chk->fetchColumn()) break; $slug = $base . '-' . $i++; }
                $pdo->prepare("INSERT INTO products (slug,name,category_id,price,old_price,rating,reviews,stock,is_featured,is_active,image) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$slug, $name, $cat, $price, $old, $rating, $reviews, $stock, $feat, $act, $image]);
                flash('Produit créé.');
            }
        }
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'err');
        header('Location: products.php' . (!empty($_POST['id']) ? '?edit=' . (int) $_POST['id'] : '?new=1'));
        exit;
    }
    header('Location: products.php');
    exit;
}

$cats = $pdo->query("SELECT id, name FROM categories ORDER BY sort_order, name")->fetchAll();
admin_header('Produits', 'products');

if (isset($_GET['new']) || isset($_GET['edit'])):
    $p = ['id' => 0, 'name' => '', 'category_id' => $cats[0]['id'] ?? 0, 'price' => '', 'old_price' => '', 'rating' => 5,
          'reviews' => 0, 'stock' => 100, 'is_featured' => 0, 'is_active' => 1, 'image' => ''];
    if (isset($_GET['edit'])) {
        $st = $pdo->prepare("SELECT * FROM products WHERE id=?");
        $st->execute([(int) $_GET['edit']]);
        $p = $st->fetch() ?: $p;
    }
?>
<div class="card"><form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
<label>Nom</label><input name="name" required maxlength="150" value="<?= h($p['name']) ?>">
<div class="grid">
 <div><label>Catégorie</label><select name="category_id"><?php foreach ($cats as $c): ?>
   <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) $p['category_id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></div>
 <div><label>Prix (DA)</label><input name="price" required value="<?= h($p['price']) ?>"></div>
 <div><label>Ancien prix (optionnel)</label><input name="old_price" value="<?= h($p['old_price']) ?>"></div>
 <div><label>Stock</label><input type="number" min="0" name="stock" value="<?= (int) $p['stock'] ?>"></div>
 <div><label>Note (1-5)</label><input type="number" min="1" max="5" name="rating" value="<?= (int) $p['rating'] ?>"></div>
 <div><label>Nombre d'avis</label><input type="number" min="0" name="reviews" value="<?= (int) $p['reviews'] ?>"></div>
</div>
<label>Image (URL https://… ou chemin)</label><input name="image" value="<?= h($p['image']) ?>">
<label>ou téléverser une image (JPG/PNG/WebP, 3 Mo max)</label><input type="file" name="image_file" accept="image/jpeg,image/png,image/webp">
<label><input type="checkbox" name="is_featured" style="width:auto" <?= $p['is_featured'] ? 'checked' : '' ?>> Afficher dans « Les meilleures ventes » (accueil)</label>
<label><input type="checkbox" name="is_active" style="width:auto" <?= $p['is_active'] ? 'checked' : '' ?>> Visible sur le site</label>
<div class="row" style="margin-top:20px"><button class="btn" type="submit">Enregistrer</button><a class="btn sec" href="products.php">Annuler</a></div>
</form></div>
<?php else:
    $rows = $pdo->query("SELECT p.*, c.name AS cat FROM products p JOIN categories c ON c.id=p.category_id ORDER BY p.id DESC")->fetchAll();
?>
<p><a class="btn" href="?new=1">+ Nouveau produit</a></p>
<div class="card"><table><tr><th></th><th>Produit</th><th>Catégorie</th><th>Prix</th><th>Stock</th><th>État</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?php if ($r['image']): ?><img class="th" src="<?= h(preg_match('#^https?://#', $r['image']) ? $r['image'] : '../' . ltrim($r['image'], '/')) ?>" alt=""><?php endif; ?></td>
<td><b><?= h($r['name']) ?></b><br><small style="color:#6b7280"><?= h($r['slug']) ?><?= $r['is_featured'] ? ' · ★ phare' : '' ?></small></td>
<td><?= h($r['cat']) ?></td><td><?= h(fmt_price($r['price'])) ?><?php if ($r['old_price']): ?><br><s style="color:#9ca3af"><?= h(fmt_price($r['old_price'])) ?></s><?php endif; ?></td>
<td><?= (int) $r['stock'] ?></td><td><?= $r['is_active'] ? 'Visible' : 'Masqué' ?></td>
<td><div class="row"><a class="btn sec" href="?edit=<?= (int) $r['id'] ?>">Modifier</a>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn sec"><?= $r['is_active'] ? 'Masquer' : 'Afficher' ?></button></form>
<form method="post" onsubmit="return confirm('Supprimer ce produit ?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn red">Supprimer</button></form></div></td></tr>
<?php endforeach; ?></table></div>
<?php endif;
admin_footer();
