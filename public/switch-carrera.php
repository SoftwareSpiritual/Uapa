<?php

declare(strict_types=1);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/functions.php';

$pdo = get_db();
$carreras = get_all_carreras($pdo);

$slug = $_GET['slug'] ?? '';
foreach ($carreras as $carrera) {
    if ($carrera['slug'] === $slug) {
        setcookie('carrera', $slug, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => '/',
            'samesite' => 'Lax',
        ]);
        break;
    }
}

$redirect = $_GET['redirect'] ?? 'index.php';
if (!in_array($redirect, ['index.php', 'pensum.php'], true)) {
    $redirect = 'index.php';
}

header('Location: ' . $redirect);
exit;
