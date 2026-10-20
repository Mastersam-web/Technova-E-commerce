<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');
ini_set('display_errors', config('debug') ? '1' : '0');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e) {
    error_log('[TechNova] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (defined('IS_API')) {
        $msg = config('debug') ? $e->getMessage() : 'Erreur serveur, veuillez réessayer plus tard.';
        json_out(['ok' => false, 'message' => $msg], 500);
    }
    http_response_code(500);
    echo config('debug') ? h($e->getMessage()) : 'Erreur serveur.';
    exit;
});
