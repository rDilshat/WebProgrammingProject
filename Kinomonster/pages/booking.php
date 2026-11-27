<?php

require __DIR__ . '/../config/session.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../login.html?status=invalid_credentials');
    exit;
}
$showtimeId = (int) ($_POST['showtime_id'] ?? 0);
$movieSlug = trim((string) ($_POST['movie_id'] ?? ''));
$seats = array_values(array_unique(array_map('intval', $_POST['seats'] ?? [])));

try {
    $pdo = require __DIR__ . '/../config/database.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $statement = $pdo->prepare(
        'SELECT s.id, s.movie_id, s.starts_at, s.price,
                h.capacity, m.slug, m.title
         FROM showtimes s
         JOIN halls h ON h.id = s.hall_id
         JOIN movies m ON m.id = s.movie_id
         WHERE s.id = :showtime_id
           AND m.slug = :movie_slug
           AND m.is_active = TRUE
           AND s.starts_at >= NOW()'
    );
    $statement->execute([
        'showtime_id' => $showtimeId,
        'movie_slug' => $movieSlug,
    ]);
    $showtime = $statement->fetch();

    if (!$showtime
        || $seats === []
        || count($seats) > (int) $showtime['capacity']
        || min($seats) < 1
        || max($seats) > (int) $showtime['capacity']) {
        header('Location: movie.php?id=' . urlencode($movieSlug) . '&error=invalid_booking');
        exit;
    }

    $pdo->beginTransaction();
    $statement = $pdo->prepare(
        "INSERT INTO bookings (user_id, showtime_id, status, total_price)
         VALUES (:user_id, :showtime_id, 'confirmed', :total_price)
         RETURNING id"
    );
    $statement->execute([
        'user_id' => $_SESSION['user']['id'],
        'showtime_id' => $showtimeId,
        'total_price' => (float) $showtime['price'] * count($seats),
    ]);
    $bookingId = (int) $statement->fetchColumn();

    $seatStatement = $pdo->prepare(
        'INSERT INTO booking_seats (booking_id, showtime_id, seat_number, is_active)
         VALUES (:booking_id, :showtime_id, :seat_number, TRUE)'
    );
    foreach ($seats as $seat) {
        $seatStatement->execute([
            'booking_id' => $bookingId,
            'showtime_id' => $showtimeId,
            'seat_number' => $seat,
        ]);
    }

    $pdo->commit();
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->getMessage());
    $status = $exception->getCode() === '23505' ? 'seats_taken' : 'booking_error';
    header('Location: movie.php?id=' . urlencode($movieSlug) . '&error=' . $status);
    exit;
}

$_SESSION['booking'] = [
    'id' => $bookingId,
    'movie_slug' => $movieSlug,
    'date' => date('Y-m-d', strtotime($showtime['starts_at'])),
    'session' => date('H:i', strtotime($showtime['starts_at'])),
    'seats' => $seats,
];
header('Location: confirmation.php');
exit;
