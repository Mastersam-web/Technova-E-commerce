<?php
declare(strict_types=1);
/**
 * Installateur TechNova — à exécuter UNE fois : http://votre-site/install.php
 * 1) Renseignez includes/config.php  2) ouvrez cette page  3) créez le compte admin.
 * Une fois installé, la page se verrouille ; supprimez ensuite ce fichier.
 */
require __DIR__ . '/includes/bootstrap.php';

$msg = ''; $err = ''; $installed = false; $needTables = true;

try {
    $pdo = db();
    $needTables = !$pdo->query("SHOW TABLES LIKE 'admins'")->fetch();
    if (!$needTables) {
        $installed = (int) $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn() > 0;
    }
} catch (Throwable $e) {
    $err = 'Connexion MySQL impossible. Vérifiez includes/config.php et que la base "' . config('db.name') . '" existe. (' . (config('debug') ? $e->getMessage() : 'activez debug pour le détail') . ')';
}

if (!$err && !$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = clean($_POST['username'] ?? '', 50);
    $pass = (string) ($_POST['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $user)) {
        $err = "Identifiant invalide (3-50 caractères : lettres, chiffres, . _ -).";
    } elseif (strlen($pass) < 10) {
        $err = 'Mot de passe trop court (10 caractères minimum).';
    } else {
        try {
            if ($needTables) {
                $sql = file_get_contents(__DIR__ . '/database/schema.sql');
                // Découpe par ';' en fin de ligne (le schéma n'utilise ni procédure ni délimiteur personnalisé)
                foreach (preg_split('/;\s*[\r\n]+/', $sql) as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt !== '' && !preg_match('/^(--[^\n]*\s*)+$/', $stmt)) {
                        $pdo->exec($stmt);
                    }
                }
            }
            $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)")
                ->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);
            $installed = true;
            $msg = 'Installation terminée ! Supprimez maintenant install.php, puis connectez-vous sur /admin/.';
        } catch (Throwable $e) {
            $err = 'Erreur pendant l\'installation : ' . (config('debug') ? $e->getMessage() : 'consultez le journal PHP.');
            error_log('[TechNova install] ' . $e->getMessage());
        }
    }
}
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Installation TechNova</title>
<style>body{font-family:system-ui,sans-serif;background:#f8faf9;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}
.c{background:#fff;border:1px solid #e5e7eb;border-radius:20px;padding:36px;width:420px}h1{color:#0e5a47;margin-top:0}
label{display:block;font-weight:600;margin:12px 0 4px;font-size:13px}input{width:100%;padding:11px;border:1px solid #d1d5db;border-radius:10px;box-sizing:border-box}
button{width:100%;margin-top:20px;background:#0e5a47;color:#fff;border:0;padding:12px;border-radius:12px;font-weight:700;cursor:pointer}
.e{background:#fee2e2;color:#991b1b;padding:10px;border-radius:10px}.o{background:#d1fae5;color:#065f46;padding:10px;border-radius:10px}</style></head><body><div class="c">
<h1>Installation TechNova</h1>
<?php if ($err): ?><div class="e"><?= h($err) ?></div><?php endif; ?>
<?php if ($msg): ?><div class="o"><?= h($msg) ?></div><p><a href="admin/login.php">Aller à l'administration →</a></p>
<?php elseif ($installed): ?><div class="o">Déjà installé. Supprimez install.php de votre serveur.</div>
<?php elseif (!$err || $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
<p>Crée les tables, ajoute les produits du site et votre compte administrateur.</p>
<form method="post" autocomplete="off"><label>Identifiant admin</label><input name="username" required>
<label>Mot de passe (10 caractères min.)</label><input name="password" type="password" minlength="10" required>
<button>Installer</button></form>
<?php endif; ?></div></body></html>
