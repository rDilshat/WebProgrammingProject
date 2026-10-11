<?php

$movies = require __DIR__ . '/../data/movies.php';
$id = $_GET['id'] ?? '';
$movie = $movies[$id] ?? null;
$movieBookedSeats = $movie['booked'] ?? [];

if ($movie === null) {
    http_response_code(404);
}

// Места, уже забронированные в базе: "дата|сеанс" => [номера мест]
$dbBookedSeats = [];

if ($movie !== null) {
    try {
        $pdo = require __DIR__ . '/../config/database.php';
        $statement = $pdo->prepare(
            "SELECT booking_date, session_time, seats
             FROM bookings
             WHERE movie_id = :movie_id AND booking_date >= CURRENT_DATE AND status <> 'cancelled'"
        );
        $statement->execute(['movie_id' => $id]);

        foreach ($statement->fetchAll() as $row) {
            $key = $row['booking_date'] . '|' . $row['session_time'];
            $seats = array_map('intval', explode(',', $row['seats']));
            $dbBookedSeats[$key] = array_merge($dbBookedSeats[$key] ?? [], $seats);
        }
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $movie ? htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') : 'Movie not found' ?> — KinoMonster</title>
  <link rel="stylesheet" href="../css/main.css">
  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/footer.css">
  <script src="../js/main.js" defer></script>
</head>
<body>
<header>
  <nav class="header-nav nav-desktop" aria-label="Main navigation">
    <div class="header-nav-elems default-container">
      <ul>
        <li>
          <a href="../project.php" class="logo">
            <img src="../photos/logo_kinomonster.svg" alt="KinoMonster">
          </a>
        </li>
      </ul>
      <ul class="second-menu">
        <li><a href="../project.php">HOME</a></li>
        <li><a href="search.php" class="active" aria-current="page">MOVIES</a></li>
        <li><a href="../login.html">LOG IN</a></li>
        <li><a href="../register.html">REGISTER</a></li>
      </ul>
    </div>
  </nav>

  <nav class="nav-mobile" aria-label="Mobile navigation">
    <div class="header-nav-elems-mobile">
      <ul>
        <li class="mobile-btn">
          <img src="../photos/menuicon.svg" alt="Menu">
        </li>
        <li>
          <a href="../project.php" class="logo">
            <img src="../photos/logo_kinomonster.svg" alt="KinoMonster">
          </a>
        </li>
      </ul>
      <ul class="second-menu-mobile">
        <li><a href="../project.php">HOME</a></li>
        <li><a href="search.php" class="active" aria-current="page">MOVIES</a></li>
        <li><a href="../login.html">LOG IN</a></li>
        <li><a href="../register.html">REGISTER</a></li>
      </ul>
    </div>
  </nav>
</header>
  <main class="flow-page default-container">
    <?php if ($movie === null): ?>
      <section class="empty-state"><h1>Movie not found</h1><a class="text-button" href="search.php">BACK TO SEARCH</a></section>
    <?php else: ?>
      <article class="movie-details">
        <img src="../photos/<?= htmlspecialchars($movie['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') ?> poster">
        <div><p class="lbl-p">Movie details</p><h1><?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') ?></h1><p><?= htmlspecialchars($movie['description'], ENT_QUOTES, 'UTF-8') ?></p><p class="movie-meta"><?= htmlspecialchars($movie['genre'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($movie['duration'], ENT_QUOTES, 'UTF-8') ?></p></div>
      </article>
      <section class="booking-panel"><p class="lbl-p">Choose a date, session and seats</p><?php if (($_GET['error'] ?? '') === 'seats_taken'): ?><p class="account-message error" role="alert">Some of these seats have just been booked. Please choose other seats.</p><?php endif; ?><form action="booking.php" method="post" data-booked-db="<?= htmlspecialchars(json_encode($dbBookedSeats), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="movie_id" value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"><label for="booking-date">Date</label><input id="booking-date" type="date" name="date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required><label for="session">Session</label><select id="session" name="session" required><?php foreach ($movie['sessions'] as $session): ?><option value="<?= htmlspecialchars($session, ENT_QUOTES, 'UTF-8') ?>" data-booked="<?= htmlspecialchars(implode(',', $movieBookedSeats[$session] ?? []), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($session, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><fieldset><legend>Seats: available, selected, occupied</legend><div class="seat-grid"><?php for ($seat = 1; $seat <= 24; $seat++): ?><label data-seat="<?= $seat ?>"><input type="checkbox" name="seats[]" value="<?= $seat ?>"><span><?= $seat ?></span></label><?php endfor; ?></div></fieldset><button type="submit">BOOK SEATS</button></form></section>
    <?php endif; ?>
  </main>
 <footer class="footer">
    <div class="footer-content">
      <div class="footer-section">
        <h4>Main links</h4>
        <a href="../project.php">Home</a>
        <a href="search.php">Movies</a>
      </div>
      <div class="footer-section">
        <h4>Account</h4>
        <a href="../login.html">Log In</a>
        <a href="../register.html">Register</a>
      </div>
    </div>
  
    <div class="social-media">
      <span>Follow us via social media:</span>
      <div class="icons">
        <a href="https://www.youtube.com" target="_blank" rel="noopener"><img src="../photos/youtube.svg" alt="YouTube"></a>
        <a href="https://www.facebook.com" target="_blank" rel="noopener"><img src="../photos/facebook.svg" alt="Facebook"></a>
        <a href="https://www.instagram.com" target="_blank" rel="noopener"><img src="../photos/instagramm.svg" alt="Instagram"></a>
        <a href="https://vk.com" target="_blank" rel="noopener"><img src="../photos/vk.svg" alt="VK"></a>
        <a href="https://telegram.org" target="_blank" rel="noopener"><img src="../photos/telegramm.svg" alt="Telegram"></a>
      </div>
    </div>
  
    <div class="footer-bottom">
      <p>© KinoMonster.com All rights reserved.</p>
    </div>
</footer>

</body>
</html>