<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$firstName = trim((string) ($_POST['firstName'] ?? ''));
$lastName = trim((string) ($_POST['lastName'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');
$repeatPassword = (string) ($_POST['repeatPassword'] ?? '');

$validName = static fn (string $name): bool => $name !== '' && strlen($name) <= 100;

if (
    !$validName($firstName)
    || !$validName($lastName)
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($email) > 255
    || strlen($password) < 8
    || $password !== $repeatPassword
) {
    header('Location: ../register.html?status=invalid_data');
    exit;
}

try {
    $pdo = require __DIR__ . '/../config/database.php';
    $statement = $pdo->prepare(
        'INSERT INTO users (first_name, last_name, email, password_hash)
         VALUES (:first_name, :last_name, :email, :password_hash)'
    );
    $statement->execute([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23505') {
        header('Location: ../register.html?status=email_exists');
        exit;
    }

    error_log($exception->getMessage());
    header('Location: ../register.html?status=database_error');
    exit;
}

header('Location: ../login.html?status=registered');
exit;
