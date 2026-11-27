<?php

declare(strict_types=1);

require __DIR__ . '/../config/movie_helpers.php';

$slug = trim((string) ($_GET['id'] ?? ''));
$movie = null;
$genres = [];
$showtimes = [];
$activeSeats = [];
$databaseError = false;

try {
    $pdo = require __DIR__ . '/../config/database.php';

    $statement = $pdo->prepare(
        'SELECT id, slug, title, description, duration_min, release_year, poster, trailer_url
         FROM movies
         WHERE slug = :slug AND is_active = TRUE'
    );
    $statement->execute(['slug' => $slug]);
    $movie = $statement->fetch();

    if ($movie !== false) {
        $genreStatement = $pdo->prepare(
            'SELECT g.name
             FROM genres g
             JOIN movie_genres mg ON mg.genre_id = g.id
             WHERE mg.movie_id = :movie_id
             ORDER BY g.name'
        );
        $genreStatement->execute(['movie_id' => $movie['id']]);
        $genres = array_column($genreStatement->fetchAll(), 'name');

        $showtimeStatement = $pdo->prepare(
            'SELECT s.id, s.starts_at, s.price, h.name AS hall_name, h.capacity
             FROM showtimes s
             JOIN halls h ON h.id = s.hall_id
             WHERE s.movie_id = :movie_id
               AND s.starts_at >= NOW()
             ORDER BY s.starts_at'
        );
        $showtimeStatement->execute(['movie_id' => $movie['id']]);
        $showtimes = $showtimeStatement->fetchAll();

        if ($showtimes !== []) {
            $showtimeIds = array_column($showtimes, 'id');
            $placeholders = implode(',', array_fill(0, count($showtimeIds), '?'));
            $seatStatement = $pdo->prepare(
                "SELECT showtime_id, seat_number
                 FROM booking_seats
                 WHERE is_active = TRUE
                   AND showtime_id IN ($placeholders)"
            );
            $seatStatement->execute($showtimeIds);
            foreach ($seatStatement->fetchAll() as $seat) {
                $activeSeats[(int) $seat['showtime_id']][] = (int) $seat['seat_number'];
            }
        }
    } else {
        $movie = null;
    }
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $databaseError = true;
}

if ($databaseError) {
    http_response_code(503);
} elseif ($movie === null) {
    http_response_code(404);
}

$embedUrl = $movie ? movieYouTubeEmbedUrl($movie['trailer_url']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $databaseError ? 'Movies unavailable' : ($movie ? htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') : 'Movie not found') ?> — KinoMonster</title>
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
    <?php if ($databaseError): ?>
      <section class="empty-state"><h1>Movie database is temporarily unavailable</h1><p>Please try again later.</p></section>
    <?php elseif ($movie === null): ?>
      <section class="empty-state"><h1>Movie not found</h1><a class="text-button" href="search.php">BACK TO SEARCH</a></section>
    <?php else: ?>
      <article class="movie-details">
        <img src="../photos/<?= htmlspecialchars($movie['poster'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') ?> poster">
        <div>
          <p class="lbl-p">Movie details</p>
          <h1><?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') ?></h1>
          <p><?= htmlspecialchars($movie['description'], ENT_QUOTES, 'UTF-8') ?></p>
          <p class="movie-meta"><?= htmlspecialchars(implode(', ', $genres), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) $movie['duration_min'], ENT_QUOTES, 'UTF-8') ?> min<?= $movie['release_year'] ? ' · ' . htmlspecialchars((string) $movie['release_year'], ENT_QUOTES, 'UTF-8') : '' ?></p>
        </div>
      </article>
      <?php if ($embedUrl !== null): ?>
        <section class="trailer-section" id="trailer">
          <p class="lbl-p">Trailer</p>
          <div class="trailer-player">
            <iframe src="<?= htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8') ?>"
                    title="<?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') ?> trailer"
                    loading="lazy"
                    allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen></iframe>
          </div>
        </section>
      <?php endif; ?>
      <section class="booking-panel">
        <p class="lbl-p">Choose a date, session and seats</p>
        <?php if (($_GET['error'] ?? '') === 'seats_taken'): ?>
          <p class="account-message error" role="alert">Some of these seats have just been booked. Please choose other seats.</p>
        <?php endif; ?>
        <?php if ($showtimes === []): ?>
          <p class="empty-state">There are no upcoming showtimes for this movie.</p>
        <?php else: ?>
          <form action="booking.php" method="post">
            <input type="hidden" name="movie_id" value="<?= htmlspecialchars($movie['slug'], ENT_QUOTES, 'UTF-8') ?>">
            <label for="showtime">Session</label>
            <select id="showtime" name="showtime_id" required>
              <?php foreach ($showtimes as $showtime): ?>
                <option value="<?= (int) $showtime['id'] ?>"
                        data-booked="<?= htmlspecialchars(implode(',', $activeSeats[(int) $showtime['id']] ?? []), ENT_QUOTES, 'UTF-8') ?>"
                        data-capacity="<?= (int) $showtime['capacity'] ?>">
                  <?= htmlspecialchars(date('d.m.Y H:i', strtotime($showtime['starts_at'])) . ' — ' . $showtime['hall_name'] . ' — ' . number_format((float) $showtime['price'], 0, '.', ' ') . ' KZT', ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
            <fieldset>
              <legend>Seats: available, selected, occupied</legend>
              <div class="seat-grid">
                <?php
                  $initialCapacity = (int) $showtimes[0]['capacity'];
                  for ($seat = 1; $seat <= $initialCapacity; $seat++):
                ?>
                  <label data-seat="<?= $seat ?>">
                    <input type="checkbox" name="seats[]" value="<?= $seat ?>">
                    <span><?= $seat ?></span>
                  </label>
                <?php endfor; ?>
              </div>
            </fieldset>
            <button type="submit">BOOK SEATS</button>
          </form>
        <?php endif; ?>
      </section>

      <?php if (is_file(__DIR__ . '/reviews-block.php')): ?>
        <?php include 'reviews-block.php'; ?>
      <?php endif; ?>
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