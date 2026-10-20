# TechNova — Backend PHP / MySQL

Prérequis : PHP 8.0+ (extensions pdo_mysql, mbstring, fileinfo), MySQL 5.7+ ou MariaDB 10.3+, Apache (les `.htaccess` protègent `includes/`, `database/`, `uploads/`).

## Installation (5 minutes)

1. Créez une base MySQL vide (ex. `technova`, interclassement `utf8mb4_unicode_ci`) via phpMyAdmin / cPanel.
2. Envoyez tout le contenu du dossier sur votre hébergement (ou dans `htdocs/technova` en local avec XAMPP/WAMP).
3. Éditez `includes/config.php` : identifiants MySQL, `site.url`, e-mail de réception (`mail.to`) et expéditeur (`mail.from`).
4. Ouvrez `http://votre-site/install.php` : créez votre compte admin (mot de passe ≥ 10 caractères). Les tables et les 8 produits du site sont créés automatiquement.
   *(Alternative : importer `database/schema.sql` dans phpMyAdmin, puis créer l'admin via install.php.)*
5. **Supprimez `install.php`**, puis connectez-vous sur `/admin/`.

## Ce que fait le backend

| Fonction | Endpoint | Détails |
|---|---|---|
| Produits | `GET api/products.php` | Catalogue lu depuis MySQL (remises calculées automatiquement) |
| Contact | `POST api/contact.php` | Validation, anti-spam (limite par IP + honeypot), sauvegarde en base + e-mail à `mail.to` |
| Newsletter | `POST api/newsletter.php` | Inscription unique, réinscription, e-mail de bienvenue, lien de désinscription (`unsubscribe.php`) |
| Comptes clients | `api/account.php` | Inscription, connexion, déconnexion, profil, changement de mot de passe, mot de passe oublié (lien par e-mail → `reset.php`), historique des commandes |
| Commandes | `POST api/orders.php` | **Réservé aux clients connectés**. Prix recalculés côté serveur, vérification du stock, transaction, référence `TN-AAMMJJ-XXXXXX`, e-mails admin + client |

## Administration (`/admin/`)
Tableau de bord, produits (ajout/modif/masquer/supprimer, upload d'image), commandes (statuts, remise en stock si annulée), **clients (liste, commandes, suspension de compte)**, messages de contact, abonnés newsletter (recherche + export CSV).

## Sécurité incluse
Requêtes PDO préparées, mots de passe hachés (bcrypt/argon) pour clients et admin, sessions séparées client/admin (HttpOnly, SameSite), jetons CSRF sur l'admin et requêtes JSON obligatoires côté client, liens de réinitialisation à usage unique valables 1 h (stockés hachés), limitation de débit anti-spam/brute-force, échappement HTML, upload d'images vérifié (type réel, 3 Mo, exécution PHP interdite dans `uploads/`), prix jamais pris du navigateur.

## Espace client (`/compte/`)
Page dédiée avec menu latéral, comme l'administration. On y accède par l'icône utilisateur du site (point vert = connecté, point rouge = notifications non lues) ; un visiteur non connecté est renvoyé vers la fenêtre de connexion.
- **Tableau de bord** : commandes, en cours, livrées, total dépensé, notifications non lues, dernière commande avec barre de suivi, dernières notifications, informations du compte, alerte si le profil est incomplet ;
- **Mes commandes** : liste filtrable par statut, détail (articles, livraison, suivi En attente → Confirmée → Expédiée → Livrée) et **annulation** tant que la commande est « En attente » (remise en stock automatique) ;
- **Notifications** : bienvenue, commande reçue, confirmée / expédiée / livrée / annulée (changement de statut par l'admin, avec e-mail en plus), mot de passe modifié. Marquer lu, tout marquer lu, supprimer ;
- **Mon profil** : nom, téléphone, adresse, ville (l'e-mail est l'identifiant) ;
- **Sécurité** : changement de mot de passe, dates de création et de dernière connexion ; **Se déconnecter**.

Cliquer sur « Passer la commande » sans être connecté ouvre l'inscription ; la commande reprend automatiquement après connexion. Le paiement reste à la livraison.

## Modifications du site (`index.html`)
Design inchangé : seuls le JavaScript, des attributs `name` sur les champs et une fenêtre de connexion/inscription, une fenêtre « finaliser la commande », dans le même style que le site, ont été ajoutés. L'icône utilisateur mène à `/compte/` et affiche un point vert (connecté) ou rouge (notifications non lues).

## Déjà installé une version précédente ?
- Version 1 (sans comptes clients) : importez `database/migration_comptes_clients.sql`, puis `database/migration_notifications.sql`.
- Version avec comptes clients mais sans notifications : importez seulement `database/migration_notifications.sql`.

Tous vos produits, commandes et clients sont conservés. Une nouvelle installation n'a rien à importer.

## Notes
- Si les e-mails n'arrivent pas (bienvenue, confirmation de commande, mot de passe oublié) : vérifiez que `mail.from` appartient à votre domaine. Sans e-mail, le « mot de passe oublié » ne peut pas fonctionner.
- Dans `includes/config.php`, gardez `debug` à `false` en production.
- Le site doit rester sur le même domaine que `api/` (le contrôle d'origine bloque les autres).
