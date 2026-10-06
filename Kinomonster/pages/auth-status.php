<?php

declare(strict_types=1);

require __DIR__ . '/../config/session.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = $_SESSION['user'] ?? null;

echo json_encode([
    'authenticated' => $user !== null,
    'user' => $user,
], JSON_THROW_ON_ERROR);
