<?php
/**
 * Configuration TechNova — modifiez ces valeurs avant la mise en ligne.
 */
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'technova',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'site' => [
        'name' => 'TechNova',
        'url'  => 'http://localhost/technova', // sans slash final (utilisé dans les e-mails)
    ],
    'mail' => [
        'enabled'   => true,                    // false = aucun e-mail envoyé
        'to'        => 'contact@technova.ma',   // reçoit messages de contact et commandes
        'from'      => 'no-reply@technova.ma',  // doit appartenir à votre domaine
        'from_name' => 'TechNova',
    ],
    'limits' => [
        'contact_per_10min'    => 5,
        'newsletter_per_10min' => 5,
        'order_per_10min'      => 5,
        'login_per_15min'      => 8,   // connexion admin
        'customer_login_per_15min' => 10,
        'register_per_10min'   => 5,
        'forgot_per_hour'      => 5,
    ],
    'debug' => false, // true uniquement en développement
];
