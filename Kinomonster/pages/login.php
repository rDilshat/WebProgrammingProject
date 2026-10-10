<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    header('Location: ../login.html?status=invalid_credentials');
    exit;
}

try {
    $pdo = require __DIR__ . '/../config/database.php';
    $statement = $pdo->prepare(
        'SELECT id, first_name, last_name, email, password_hash, role
         FROM users
         WHERE email = :email
         LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    header('Location: ../login.html?status=database_error');
    exit;
}

if (!$user || !password_verify($password, $user['password_hash'])) {
    header('Location: ../login.html?status=invalid_credentials');
    exit;
}

require __DIR__ . '/../config/session.php';
session_regenerate_id(true);
$_SESSION['user'] = [
    'id' => (int) $user['id'],
    'firstName' => $user['first_name'],
    'lastName' => $user['last_name'],
    'email' => $user['email'],
    'role' => $user['role'],
];

header('Location: ../project.php');
exit;
