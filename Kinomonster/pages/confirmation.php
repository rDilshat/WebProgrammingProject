<?php

session_start();
$movies = require __DIR__ . '/../data/movies.php';
$booking = $_SESSION['booking'] ?? null;
$movie = $booking ? ($movies[$booking['movie_id']] ?? null) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Confirmation — KinoMonster</title><link rel="stylesheet" href="../css/main.css"><link rel="stylesheet" href="../css/header.css"><link rel="stylesheet" href="../css/footer.css"><script src="../js/main.js" defer></script></head>
<body><header>
  <nav class="header-nav nav-desktop" aria-label="Main navigation">
    <div class="header-nav-elems default-container">
      <ul>
        <li>
          <a href="../project.html" class="logo">
            <img src="../photos/logo_kinomonster.svg" alt="KinoMonster">
          </a>
        </li>
      </ul>
      <ul class="second-menu">
        <li><a href="../project.html">HOME</a></li>
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
          <a href="../project.html" class="logo">
            <img src="../photos/logo_kinomonster.svg" alt="KinoMonster">
          </a>
        </li>
      </ul>
      <ul class="second-menu-mobile">
        <li><a href="../project.html">HOME</a></li>
        <li><a href="search.php" class="active" aria-current="page">MOVIES</a></li>
        <li><a href="../login.html">LOG IN</a></li>
        <li><a href="../register.html">REGISTER</a></li>
      </ul>
    </div>
  </nav>
</header><main class="flow-page default-container"><section class="confirmation"><?php if ($booking && $movie): ?><div class="success-icon">✓</div><p class="success-label">SUCCESS</p><h1>Your booking is confirmed!</h1><div class="booking-summary"><p><span>Booking ID</span>#<?= htmlspecialchars($booking['id'], ENT_QUOTES, 'UTF-8') ?></p><p><span>Movie</span><?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') ?></p><p><span>Date</span><?= htmlspecialchars(date('d.m.Y', strtotime($booking['date'])), ENT_QUOTES, 'UTF-8') ?></p><p><span>Time</span><?= htmlspecialchars($booking['session'], ENT_QUOTES, 'UTF-8') ?></p><p><span>Seats</span><?= htmlspecialchars(implode(', ', $booking['seats']), ENT_QUOTES, 'UTF-8') ?></p></div><a class="text-button" href="search.php">FIND ANOTHER MOVIE</a><?php else: ?><h1>No active booking</h1><a class="text-button" href="search.php">BACK TO SEARCH</a><?php endif; ?></section></main>
 <footer class="footer">
    <div class="footer-content">
      <div class="footer-section">
        <h4>Main links</h4>
        <a href="../project.html">Home</a>
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
</body></html>