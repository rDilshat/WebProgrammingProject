<?php

require __DIR__ . '/../config/session.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../login.html?status=invalid_credentials');
    exit;
}
$movies = require __DIR__ . '/../data/movies.php';
$movieId = $_POST['movie_id'] ?? '';
$date = $_POST['date'] ?? '';
$session = $_POST['session'] ?? '';
$seats = array_values(array_unique(array_map('intval', $_POST['seats'] ?? [])));
$bookedSeats = isset($movies[$movieId]) ? ($movies[$movieId]['booked'][$session] ?? []) : [];
$dateObject = DateTime::createFromFormat('!Y-m-d', $date);
$validDate = $dateObject && $dateObject->format('Y-m-d') === $date && $date >= date('Y-m-d');

if (!isset($movies[$movieId]) || !$validDate || !in_array($session, $movies[$movieId]['sessions'], true) || $seats === [] || count($seats) > 24 || min($seats) < 1 || max($seats) > 24 || array_intersect($seats, $bookedSeats) !== []) {
    header('Location: movie.php?id=' . urlencode($movieId));
    exit;
}

// Сохраняем бронь в базу, чтобы она была в профиле и в админке
try {
    $pdo = require __DIR__ . '/../config/database.php';

    // Проверяем, не заняты ли выбранные места на эту дату и сеанс
    $statement = $pdo->prepare(
        "SELECT seats FROM bookings
         WHERE movie_id = :movie_id AND booking_date = :booking_date
           AND session_time = :session_time AND status <> 'cancelled'"
    );
    $statement->execute(['movie_id' => $movieId, 'booking_date' => $date, 'session_time' => $session]);
    $takenSeats = [];
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $row) {
        $takenSeats = array_merge($takenSeats, array_map('intval', explode(',', $row)));
    }

    if (array_intersect($seats, $takenSeats) !== []) {
        header('Location: movie.php?id=' . urlencode($movieId) . '&error=seats_taken');
        exit;
    }

    $statement = $pdo->prepare(
        'INSERT INTO bookings (user_id, movie_id, booking_date, session_time, seats)
         VALUES (:user_id, :movie_id, :booking_date, :session_time, :seats)
         RETURNING id'
    );
    $statement->execute([
        'user_id' => $_SESSION['user']['id'],
        'movie_id' => $movieId,
        'booking_date' => $date,
        'session_time' => $session,
        'seats' => implode(', ', $seats),
    ]);
    $bookingId = (int) $statement->fetchColumn();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    header('Location: movie.php?id=' . urlencode($movieId));
    exit;
}

$_SESSION['booking'] = ['id' => $bookingId, 'movie_id' => $movieId, 'date' => $date, 'session' => $session, 'seats' => $seats];
header('Location: confirmation.php');
exit;
