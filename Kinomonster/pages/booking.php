<?php

session_start();
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

$_SESSION['booking'] = ['id' => random_int(10000, 99999), 'movie_id' => $movieId, 'date' => $date, 'session' => $session, 'seats' => $seats];
header('Location: confirmation.php');
exit;