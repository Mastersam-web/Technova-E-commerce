<?php
declare(strict_types=1);
define('IS_API', true);
require __DIR__ . '/../includes/bootstrap.php';

require_method('POST');
$in = read_input();

// Honeypot : un robot remplira ce champ caché, pas un humain.
if (!empty($in['website'])) {
    json_out(['ok' => true, 'message' => 'Votre message a été envoyé à l\'équipe TechNova !']);
}

$name    = clean($in['name'] ?? '', 120);
$email   = clean($in['email'] ?? '', 190);
$subject = clean($in['subject'] ?? '', 200);
$message = clean($in['message'] ?? '', 5000);

$errors = [];
if (mb_strlen($name) < 2)                          $errors['name'] = 'Nom invalide.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $errors['email'] = 'Adresse e-mail invalide.';
if (mb_strlen($subject) < 3)                       $errors['subject'] = 'Sujet trop court.';
if (mb_strlen($message) < 10)                      $errors['message'] = 'Message trop court (10 caractères minimum).';
if ($errors) {
    json_out(['ok' => false, 'message' => reset($errors), 'errors' => $errors], 422);
}

if (!rate_limit('contact', (int) config('limits.contact_per_10min', 5), 600)) {
    json_out(['ok' => false, 'message' => 'Trop de messages envoyés. Réessayez dans quelques minutes.'], 429);
}

db()->prepare("INSERT INTO contact_messages (name, email, subject, message, ip) VALUES (?,?,?,?,?)")
    ->execute([$name, $email, $subject, $message, client_ip()]);

send_mail(
    (string) config('mail.to'),
    '[TechNova] Nouveau message : ' . $subject,
    "Nom : $name\nE-mail : $email\nSujet : $subject\n\n$message\n",
    $email
);

json_out(['ok' => true, 'message' => 'Votre message a été envoyé à l\'équipe TechNova !']);
